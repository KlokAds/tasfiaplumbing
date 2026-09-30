<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'c_bread_title',
        'c_bread_img',
        'm_title',
        'm_btn_name',
        'title',
        'a_title',
        'address',
        'e_title',
        'email',
        'p_title',
        'phone',
        'map',
        'social_one',
        'social_one_link',
        'social_two',
        'social_two_link',
        'social_three',
        'social_three_link',
        'social_four',
        'social_four_link',
        'social_five',
        'social_five_link',
        'meta_title',
        'meta_desc',
        'meta_tag',
    ];
}
