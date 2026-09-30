<?php

namespace App\Http\Controllers\Admin;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\Message;
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

        return Inertia::render('Admin/Messages/Index', [
            'messages' => $query->paginate(PerPage::get($request, 25))->withQueryString(),
            // "Open in admin" from the email: show that enquiry straight away.
            'openMessage' => $request->filled('open') ? Message::find($request->integer('open')) : null,
            'filters' => $request->only(['search', 'unread', 'per_page']),
        ]);
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
