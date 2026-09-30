<?php

namespace App\Http\Controllers;

use App\Models\Message;

/**
 * The 1×1 image inside the enquiry email. When the email is opened (and the mail app loads
 * images), the enquiry is marked as read in the admin.
 */
class MailSeenController extends Controller
{
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function show(int $message)
    {
        Message::whereKey($message)->where('is_read', 0)->update(['is_read' => 1]);

        return response(base64_decode(self::GIF), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
