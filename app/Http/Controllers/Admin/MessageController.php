<?php

namespace App\Http\Controllers\Admin;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Support\Enquiries;
use App\Support\SpamCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $query = Message::latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('subject', 'like', "%{$s}%")
                  ->orWhere('message', 'like', "%{$s}%");
            });
        }

        if ($request->has('unread') && $request->unread == 'true' && Schema::hasColumn('messages', 'is_read')) {
            $query->where('is_read', 0);
        }

        // Spam folder, or the inbox (everything else), optionally one follow-up status.
        $spam = $request->input('box') === 'spam';
        $query->where('is_spam', $spam);
        if (!$spam && array_key_exists($request->input('status'), Enquiries::STATUSES)) {
            $query->where('status', $request->input('status'));
        }

        return Inertia::render('Admin/Messages/Index', [
            'messages' => $query->paginate(PerPage::get($request, 25))->withQueryString(),
            // "Open in admin" from the email: show that enquiry straight away.
            'openMessage' => $request->filled('open') ? Message::find($request->integer('open')) : null,
            'filters' => $request->only(['search', 'unread', 'per_page', 'box', 'status']),
            'statuses' => Enquiries::STATUSES,
            'statusCounts' => Message::notSpam()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'spamCount' => Message::where('is_spam', true)->count(),
            'templates' => Enquiries::templates(),
            'brand' => \App\Models\SiteSetting::get('business.brand_name') ?: config('app.name'),
        ]);
    }

    /** New → contacted → quoted → won / lost. Replying from the enquiry sets "contacted". */
    public function status(Request $request, Message $message)
    {
        $status = $request->validate(['status' => ['required', \Illuminate\Validation\Rule::in(array_keys(Enquiries::STATUSES))]])['status'];
        $message->update(['status' => $status, 'status_at' => now(), 'is_read' => 1]);

        return back()->with('success', 'Marked “' . Enquiries::STATUSES[$status] . '”.');
    }

    /** Move to or out of the Spam folder. Nothing is deleted. */
    public function spam(Request $request, Message $message)
    {
        $spam = $request->validate(['spam' => 'required|boolean'])['spam'];
        $message->update(['is_spam' => $spam, 'spam_reason' => $spam ? 'Marked as spam by ' . $request->user()->name . '.' : null]);

        return back()->with('success', $spam ? 'Moved to Spam.' : 'Moved back to the inbox.');
    }

    /** Check the enquiries of the last 6 months that are still "New" and move the spam ones (nothing is deleted). */
    public function scanSpam()
    {
        $n = 0;
        foreach (Message::notSpam()->where('status', 'new')->where('created_at', '>=', now()->subMonths(6))->get() as $m) {
            [$isSpam, $why] = SpamCheck::check($m->only(['name', 'email', 'phone', 'subject', 'message']), $m->id);
            if ($isSpam) {
                $m->update(['is_spam' => true, 'spam_reason' => $why]);
                $n++;
            }
        }

        return back()->with('success', $n ? "{$n} enquiries moved to Spam. Open Spam to check them; “Not spam” brings one back." : 'No spam found among the open enquiries.');
    }

    /** The ready replies (WhatsApp / email) shown on each enquiry. */
    public function templates(Request $request)
    {
        $data = $request->validate([
            'templates' => 'present|array|max:12',
            'templates.*.name' => 'required|string|max:60',
            'templates.*.text' => 'required|string|max:1000',
        ]);
        \App\Models\SiteSetting::putMany(['enquiries.templates' => json_encode(array_values($data['templates']))]);

        return back()->with('success', 'Ready replies saved.');
    }

    public function markAsRead($id)
    {
        $message = Message::findOrFail($id);
        if (Schema::hasColumn('messages', 'is_read')) {
            $message->update(['is_read' => 1]);
        }

        return redirect()->back()->with('success', 'Message marked as read.');
    }

    public function destroy($id)
    {
        $message = Message::findOrFail($id);
        $message->delete();

        return redirect()->back()->with('success', 'Message deleted successfully.');
    }
}
