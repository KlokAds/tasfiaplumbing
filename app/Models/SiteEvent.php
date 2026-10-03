<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One page view or click counted by the website's own visitor counter (App\Support\VisitorStats). */
class SiteEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['visit', 'whatsapp', 'call', 'chat', 'chat_message'];

    // "day" stays a plain Y-m-d string (a date cast would store a time too, and day filters would miss it).
    protected $guarded = ['id'];
}
