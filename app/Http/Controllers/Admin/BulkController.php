<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Bulk delete for every admin list. Each item goes through that list's own destroy()
 * action, so the same rules apply as for a single delete (permissions, "in use" checks,
 * 410 redirects for removed pages, "cannot delete yourself" …).
 */
class BulkController extends Controller
{
    /** resource => [controller, model, destroy() parameter ("id" = plain id, else the model), permission, label] */
    private const RESOURCES = [
        'blogs' => [BlogController::class, \App\Models\BlogDetail::class, 'blog', 'articles.create', 'article'],
        'services' => [ServiceController::class, \App\Models\ServiceDetail::class, 'id', 'services.delete', 'service'],
        'service-categories' => [ServiceCategoryController::class, \App\Models\ServiceCategory::class, 'category', 'categories.delete', 'category'],
        'locations' => [LocationController::class, \App\Models\Location::class, 'location', 'locations.delete', 'area page'],
        'pricing' => [PriceController::class, \App\Models\Price::class, 'price', 'pricing.delete', 'price'],
        'faqs' => [FaqController::class, \App\Models\Faq::class, 'faq', 'faqs.delete', 'FAQ'],
        'projects' => [ProjectController::class, \App\Models\ProjectDetail::class, 'id', 'projects.delete', 'project'],
        'reviews' => [ReviewController::class, \App\Models\FeedBackContent::class, 'review', 'reviews.delete', 'review'],
        'redirects' => [RedirectController::class, \App\Models\Redirect::class, 'redirect', 'redirects.delete', 'redirect'],
        'messages' => [MessageController::class, \App\Models\Message::class, 'id', 'enquiries.delete', 'enquiry'],
        'users' => [UserController::class, \App\Models\User::class, 'user', 'users.delete', 'user'],
        'partners' => [PartnerController::class, \App\Models\Partner::class, 'id', 'homepage.edit', 'logo'],
    ];

    public function destroy(Request $request, string $resource)
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);
        [$controller, $model, $param, $permission, $label] = self::RESOURCES[$resource];
        abort_unless($request->user()->can($permission), 403);

        $ids = $request->validate(['ids' => 'required|array|min:1|max:500', 'ids.*' => 'integer'])['ids'];

        $deleted = 0;
        $skipped = 0;
        foreach (array_unique($ids) as $id) {
            try {
                $found = $model::find($id);
                if (!$found) {
                    continue;
                }
                app()->call([app($controller), 'destroy'], [$param => $param === 'id' ? $id : $found]);
                $gone = !$model::whereKey($id)->exists();
                $gone ? $deleted++ : $skipped++;
            } catch (HttpExceptionInterface $e) {
                $skipped++; // e.g. not allowed for this item
            } catch (\Throwable $e) {
                Log::warning('Bulk delete item failed', ['resource' => $resource, 'id' => $id, 'error' => $e->getMessage()]);
                $skipped++;
            }
        }

        // The per-item actions each set their own message; replace them with one summary.
        session()->forget(['success', 'error']);
        $plural = fn ($n) => $n === 1 ? $label : (preg_match('/[^aeiou]y$/', $label) ? substr($label, 0, -1) . 'ies' : $label . 's');
        $message = $deleted ? "{$deleted} {$plural($deleted)} deleted." : 'Nothing was deleted.';
        if ($skipped) {
            $message .= " {$skipped} could not be deleted (in use, not allowed, or protected).";
        }

        return back()->with($deleted ? 'success' : 'error', $message);
    }
}
