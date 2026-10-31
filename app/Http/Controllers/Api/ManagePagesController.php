<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Admin\CacheController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\SeoSettingController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Response as InertiaResponse;

/**
 * The billing app shows some of this website's own admin pages (System, Enquiries, contact and business
 * settings) in its own design. It runs the same admin code here as the owner, so the rules, checks and
 * messages are exactly those of the admin: a page gives its data, an action gives its message or errors.
 */
class ManagePagesController extends Controller
{
    /** page => [admin controller, the page's own method, [action => method]] */
    private const PAGES = [
        'system' => [SystemSettingsController::class, 'index', ['update' => 'update', 'test-mail' => 'testMail']],
        'cache' => [CacheController::class, 'index', ['clear' => 'clear']],
        'enquiries' => [MessageController::class, 'index', ['status' => 'status', 'spam' => 'spam', 'read' => 'markAsRead']],
        'contact' => [SettingController::class, 'contactIndex', ['update' => 'contactUpdate']],
        'footer' => [SettingController::class, 'footerIndex', ['update' => 'footerUpdate']],
        'business' => [SeoSettingController::class, 'business', ['update' => 'updateBusiness']],
        'seo' => [SeoSettingController::class, 'index', ['update' => 'update']],
    ];

    /** Actions on one record: the id becomes that record. */
    private const RECORD = ['enquiries' => Message::class];

    public function show(Request $request, string $page)
    {
        [$controller, $method] = self::PAGES[$page] ?? abort(404);
        $sub = $this->asOwner($request, 'GET', $request->query());
        $props = $this->asCurrent($sub, function () use ($controller, $method, $sub) {
            $response = app()->call([app($controller), $method], ['request' => $sub]);
            abort_unless($response instanceof InertiaResponse, 500);
            $sub->headers->set('X-Inertia', 'true');

            return $response->toResponse($sub)->getData(true)['props'] ?? [];
        });

        return response()->json(['props' => $props]);
    }

    public function act(Request $request, string $page, string $action, ?int $id = null)
    {
        $method = (self::PAGES[$page][2] ?? [])[$action] ?? abort(404);
        $sub = $this->asOwner($request, 'POST', $request->except(['_token']));
        $args = ['request' => $sub];
        if ($id !== null) {
            $model = (self::RECORD[$page] ?? abort(404))::findOrFail($id);
            $args += ['message' => $model, 'id' => $id];
        }
        $session = app('session.store');
        $session->forget(['success', 'error']);
        try {
            $response = $this->asCurrent($sub, fn () => app()->call([app(self::PAGES[$page][0]), $method], $args));
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first(), 'errors' => $e->errors()], 422);
        }
        if ($response instanceof JsonResponse) {
            return $response;
        }
        $error = $session->pull('error');
        $success = $session->pull('success');
        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        return response()->json(['ok' => true, 'message' => $success ?: 'Done.', 'redirect' => $response instanceof RedirectResponse]);
    }

    /** A request to the admin code made by the owner (the Super Admin), with this request's input. */
    private function asOwner(Request $request, string $verb, array $input): Request
    {
        $owner = User::where('is_active', true)->role(config('admin.super_role'))->orderByDesc('is_hidden')->orderBy('id')->firstOrFail();
        $sub = Request::create('/admin', $verb, $input, [], $request->allFiles(), ['REMOTE_ADDR' => $request->ip()]);
        $sub->setUserResolver(fn () => $owner);
        $sub->setLaravelSession(app('session.store'));
        Auth::setUser($owner);

        return $sub;
    }

    /** Runs the admin code with $sub as the current request, then puts this one back. */
    private function asCurrent(Request $sub, callable $run): mixed
    {
        $current = app('request');
        app()->instance('request', $sub);
        try {
            return $run();
        } finally {
            app()->instance('request', $current);
        }
    }
}
