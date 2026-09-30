<?php

namespace App\Http\Controllers\Admin;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RedirectController extends Controller
{
    public function index(Request $request)
    {
        $redirects = Redirect::query()
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w
                ->where('from_path', 'like', '%' . $request->search . '%')
                ->orWhere('to_path', 'like', '%' . $request->search . '%')))
            ->when($request->filled('code'), fn ($q) => $q->where('code', $request->integer('code')))
            ->orderByDesc('updated_at')
            ->paginate(PerPage::get($request, 25), ['*'], 'page')
            ->withQueryString();

        $fromSet = Redirect::pluck('from_path')->flip();
        $redirects->getCollection()->transform(fn (Redirect $r) => $r->toArray() + [
            'is_chain' => $r->to_path !== null && isset($fromSet[$r->to_path]),
        ]);

        $notFound = NotFoundLog::query()
            ->where('is_resolved', false)
            ->orderByDesc('hits')
            ->paginate(PerPage::get($request, 25), ['*'], 'nf_page')
            ->withQueryString();

        return Inertia::render('Admin/Redirects/Index', [
            'redirects' => $redirects,
            'notFound' => $notFound,
            'filters' => $request->only(['search', 'code', 'tab', 'per_page']),
            'stats' => [
                'total' => Redirect::count(),
                'permanent' => Redirect::where('code', 301)->count(),
                'gone' => Redirect::where('code', 410)->count(),
                'hits_30d' => (int) Redirect::where('last_hit_at', '>=', now()->subDays(30))->sum('hit_count'),
                'open_404' => NotFoundLog::where('is_resolved', false)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Redirect::create($data + ['source' => 'manual']);

        return redirect()->back()->with('success', "Redirect saved: {$data['from_path']}");
    }

    public function update(Request $request, Redirect $redirect)
    {
        $redirect->update($this->validated($request, $redirect->id));

        return redirect()->back()->with('success', 'Redirect updated.');
    }

    public function destroy(Redirect $redirect)
    {
        $redirect->delete();

        return redirect()->back()->with('success', 'Redirect removed. The old URL will now return 404.');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $created = $updated = 0;
        $skipped = [];
        $line = 0;

        while (($row = fgetcsv($handle)) !== false && $line < 5000) {
            $line++;
            if ($line === 1 && isset($row[0]) && stripos($row[0], 'from') !== false) {
                continue;
            }
            $from = Redirect::normalize($row[0] ?? null);
            $to = Redirect::normalize($row[1] ?? null);
            $code = (int) ($row[2] ?? 0) ?: ($to ? 301 : 410);

            if (!$from) {
                $skipped[] = "Line {$line}: empty source URL";
                continue;
            }
            if (!in_array($code, [301, 302, 410], true)) {
                $skipped[] = "Line {$line}: code {$code} not allowed";
                continue;
            }
            if ($code !== 410 && (!$to || $to === $from)) {
                $skipped[] = "Line {$line}: target missing or same as source";
                continue;
            }
            if ($to === '/' && $code !== 410) {
                $skipped[] = "Line {$line}: {$from} points to the homepage (Google treats this as a soft 404). Map it to a relevant page or use 410.";
                continue;
            }

            $redirect = Redirect::updateOrCreate(
                ['from_path' => $from],
                ['to_path' => $code === 410 ? null : $to, 'code' => $code, 'source' => 'imported', 'is_active' => true],
            );
            $redirect->wasRecentlyCreated ? $created++ : $updated++;
        }
        fclose($handle);

        $flattened = $this->flattenChains();

        $message = "Import finished: {$created} created, {$updated} updated, " . count($skipped) . ' skipped.';
        if ($flattened) {
            $message .= " {$flattened} redirect chain(s) flattened.";
        }

        return redirect()->back()
            ->with('success', $message)
            ->with('error', $skipped ? implode("\n", array_slice($skipped, 0, 25)) : null);
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['from', 'to', 'code', 'hits', 'last_hit_at', 'source', 'active']);
            Redirect::orderBy('from_path')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [$r->from_path, $r->to_path, $r->code, $r->hit_count, $r->last_hit_at, $r->source, $r->is_active ? 1 : 0]);
                }
            });
            fclose($out);
        }, 'redirects-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function resolveNotFound(NotFoundLog $log)
    {
        $log->update(['is_resolved' => true]);

        return redirect()->back()->with('success', "Ignored {$log->path}");
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->merge([
            'from_path' => Redirect::normalize($request->input('from_path')),
            'to_path' => Redirect::normalize($request->input('to_path')),
        ]);

        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:512', Rule::unique('redirects', 'from_path')->ignore($ignoreId)],
            'to_path' => 'nullable|required_unless:code,410|string|max:512|different:from_path',
            'code' => ['required', 'integer', Rule::in([301, 302, 410])],
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $data['code'] = (int) $data['code'];
        if ($data['code'] === 410) {
            $data['to_path'] = null;
        } else {
            $data['to_path'] = $this->finalTarget($data['to_path'], $data['from_path']);
        }

        return $data;
    }

    /** Follow existing redirects so the new one points straight at the final URL. */
    private function finalTarget(string $to, string $from): string
    {
        $seen = [$from => true];
        for ($i = 0; $i < 10; $i++) {
            $next = Redirect::where('from_path', $to)->where('is_active', true)->whereNotNull('to_path')->value('to_path');
            if (!$next) {
                break;
            }
            if (isset($seen[$next])) {
                throw ValidationException::withMessages(['to_path' => 'This would create a redirect loop.']);
            }
            $seen[$to] = true;
            $to = $next;
        }

        return $to;
    }

    private function flattenChains(): int
    {
        $map = Redirect::where('is_active', true)->whereNotNull('to_path')->pluck('to_path', 'from_path')->all();
        $fixed = 0;
        foreach ($map as $from => $to) {
            $final = $to;
            $hops = 0;
            while (isset($map[$final]) && $hops < 10 && $map[$final] !== $from) {
                $final = $map[$final];
                $hops++;
            }
            if ($final !== $to) {
                Redirect::where('from_path', $from)->update(['to_path' => $final]);
                $fixed++;
            }
        }
        if ($fixed) {
            \Illuminate\Support\Facades\Cache::forget(Redirect::CACHE_KEY);
        }

        return $fixed;
    }
}
