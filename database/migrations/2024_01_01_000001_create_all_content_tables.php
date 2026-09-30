<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates all content management tables for the Tasfia Plumbing CMS.
     * Each table checks for existence before creating to support upgrade paths.
     */
    public function up(): void
    {
        // Home Page Static Content (Section titles, subtitles, meta tags)
        if (!Schema::hasTable('home_statics')) {
            Schema::create('home_statics', function (Blueprint $table) {
                $table->id();
                $table->text('h_s_title')->nullable();
                $table->text('h_s_subtitle')->nullable();
                $table->string('h_a_b_name')->nullable();
                $table->text('h_a_b_link')->nullable();
                $table->text('h_sl_title')->nullable();
                $table->text('h_sl_subtitle')->nullable();
                $table->text('h_n_title')->nullable();
                $table->text('h_n_subtitle')->nullable();
                $table->text('h_c_title')->nullable();
                $table->string('h_c_button_name')->nullable();
                $table->text('h_p_title')->nullable();
                $table->text('h_p_subtitle')->nullable();
                $table->text('h_n_c_title')->nullable();
                $table->text('h_n_c_desc')->nullable();
                $table->string('h_n_c_number')->nullable();
                $table->text('h_t_title')->nullable();
                $table->text('h_test_title')->nullable();
                $table->string('h_test_img')->nullable();
                $table->text('h_b_title')->nullable();
                $table->text('h_b_subtitle')->nullable();
                $table->text('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->text('meta_tag')->default('tag, tag');
                $table->timestamps();
            });
        }

        // Home Hero Slider Sections
        if (!Schema::hasTable('home_heroes')) {
            Schema::create('home_heroes', function (Blueprint $table) {
                $table->id();
                $table->text('title');
                $table->text('subtitle');
                $table->text('short_desc')->nullable();
                $table->string('btn_name')->default('Explore Services');
                $table->string('btn_link')->default('/services');
                $table->string('img')->nullable();
                $table->timestamps();
            });
        }

        // Home Service Cards (Legacy — now merged into service_details in new upgrade)
        if (!Schema::hasTable('home_services')) {
            Schema::create('home_services', function (Blueprint $table) {
                $table->id();
                $table->text('name');
                $table->text('short_desc');
                $table->string('btn_name');
                $table->string('img');
                $table->timestamps();
            });
        }

        // Home Counter Stats
        if (!Schema::hasTable('home_counters')) {
            Schema::create('home_counters', function (Blueprint $table) {
                $table->id();
                $table->string('c_count');
                $table->string('c_title');
                $table->string('c_subtitle')->nullable();
                $table->string('c_icon')->nullable();
                $table->timestamps();
            });
        }

        // Home Skills / Capabilities
        if (!Schema::hasTable('home_skills')) {
            Schema::create('home_skills', function (Blueprint $table) {
                $table->id();
                $table->string('s_point');
                $table->string('s_title');
                $table->string('s_subtitle')->nullable();
                $table->string('s_icon')->nullable();
                $table->timestamps();
            });
        }

        // About Page Content
        if (!Schema::hasTable('about_contents')) {
            Schema::create('about_contents', function (Blueprint $table) {
                $table->id();
                $table->string('a_bread_title')->nullable();
                $table->string('a_bread_img')->nullable();
                $table->string('title');
                $table->text('subtitle');
                $table->text('short_desc');
                $table->string('img_one')->nullable();
                $table->string('img_two')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->text('meta_tag')->nullable();
                $table->timestamps();
            });
        }

        // Service Details (Full service pages with SEO)
        if (!Schema::hasTable('service_details')) {
            Schema::create('service_details', function (Blueprint $table) {
                $table->id();
                $table->integer('order')->default(0);
                $table->string('slug')->unique();
                $table->string('name');
                $table->text('desc');
                $table->string('image')->nullable();
                $table->string('btn_name')->default('Read More');
                $table->string('bef_img')->nullable();
                $table->string('aft_img')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('meta_desc')->nullable();
                $table->text('meta_tag')->nullable();
                $table->timestamps();
            });
        }

        // Project Portfolio Items
        if (!Schema::hasTable('project_details')) {
            Schema::create('project_details', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('video')->nullable();
                $table->string('image')->nullable();
                $table->timestamps();
            });
        }

        // Blog Articles
        if (!Schema::hasTable('blog_details')) {
            Schema::create('blog_details', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('auth_name')->default('Tasfia Plumbing Team');
                $table->text('desc');
                $table->string('btn_name')->default('Read More');
                $table->string('image')->nullable();
                $table->string('slug')->unique();
                $table->string('meta_title')->nullable();
                $table->text('meta_desc')->nullable();
                $table->text('meta_tag')->nullable();
                $table->timestamps();
            });
        }

        // Testimonial / Feedback Reviews
        if (!Schema::hasTable('feed_back_contents')) {
            Schema::create('feed_back_contents', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('desc');
                $table->string('img')->nullable();
                $table->timestamps();
            });
        }

        // Contact Form Messages
        if (!Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email');
                $table->string('phone')->nullable();
                $table->string('subject')->nullable();
                $table->text('message')->nullable();
                $table->boolean('is_read')->default(0);
                $table->timestamps();
            });
        }

        // Page Breadcrumb Banners & SEO per section
        if (!Schema::hasTable('bread_cumbs')) {
            Schema::create('bread_cumbs', function (Blueprint $table) {
                $table->id();
                // Service Breadcrumb
                $table->string('s_bread_name')->nullable();
                $table->string('s_bread_image')->nullable();
                $table->text('s_meta_title')->nullable();
                $table->text('s_meta_desc')->nullable();
                $table->text('s_meta_tag')->nullable();
                // Project Breadcrumb
                $table->string('p_bread_name')->nullable();
                $table->string('p_bread_image')->nullable();
                $table->text('p_meta_title')->nullable();
                $table->text('p_meta_desc')->nullable();
                $table->text('p_meta_tag')->nullable();
                // Feedback/Testimonial Breadcrumb
                $table->string('f_bread_name')->nullable();
                $table->string('f_bread_image')->nullable();
                $table->text('f_meta_title')->nullable();
                $table->text('f_meta_desc')->nullable();
                $table->text('f_meta_tag')->nullable();
                // Blog Breadcrumb
                $table->string('b_bread_name')->nullable();
                $table->string('b_bread_image')->nullable();
                $table->text('b_meta_title')->nullable();
                $table->text('b_meta_desc')->nullable();
                $table->text('b_meta_tag')->nullable();
                $table->timestamps();
            });
        }

        // Contact Page Content & Social Links
        if (!Schema::hasTable('contact_contents')) {
            Schema::create('contact_contents', function (Blueprint $table) {
                $table->id();
                $table->string('c_bread_title')->nullable();
                $table->string('c_bread_img')->nullable();
                $table->string('m_title')->nullable();
                $table->string('m_btn_name')->nullable();
                $table->string('title')->nullable();
                $table->string('a_title')->nullable();
                $table->text('address')->nullable();
                $table->string('e_title')->nullable();
                $table->string('email')->nullable();
                $table->string('p_title')->nullable();
                $table->string('phone', 20)->nullable();
                $table->text('map')->nullable();
                $table->string('social_one')->nullable();
                $table->string('social_one_link')->nullable();
                $table->string('social_two')->nullable();
                $table->string('social_two_link')->nullable();
                $table->string('social_three')->nullable();
                $table->string('social_three_link')->nullable();
                $table->string('social_four')->nullable();
                $table->string('social_four_link')->nullable();
                $table->string('social_five')->nullable();
                $table->string('social_five_link')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('meta_desc')->nullable();
                $table->text('meta_tag')->nullable();
                $table->timestamps();
            });
        }

        // Footer Settings (Logo, Copyright, Tracking Tags)
        if (!Schema::hasTable('footers')) {
            Schema::create('footers', function (Blueprint $table) {
                $table->id();
                $table->string('g_tag')->nullable();
                $table->string('g_a_tag')->nullable();
                $table->string('c_text')->nullable();
                $table->text('f_short_desc')->nullable();
                $table->text('main_logo')->nullable();
                $table->text('f_logo')->nullable();
                $table->text('wh_one')->nullable();
                $table->text('wh_two')->nullable();
                $table->text('wh_three')->nullable();
                $table->text('wh_four')->nullable();
                $table->text('property_id')->nullable();
                $table->text('widget_id')->nullable();
                $table->timestamps();
            });
        }

        // Partner / Brand Logos
        if (!Schema::hasTable('partners')) {
            Schema::create('partners', function (Blueprint $table) {
                $table->id();
                $table->string('image');
                $table->timestamps();
            });
        }

        // Font Awesome Icons Reference (Legacy)
        if (!Schema::hasTable('font_awesomes')) {
            Schema::create('font_awesomes', function (Blueprint $table) {
                $table->id();
                $table->text('name');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('font_awesomes');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('footers');
        Schema::dropIfExists('contact_contents');
        Schema::dropIfExists('bread_cumbs');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('feed_back_contents');
        Schema::dropIfExists('blog_details');
        Schema::dropIfExists('project_details');
        Schema::dropIfExists('service_details');
        Schema::dropIfExists('about_contents');
        Schema::dropIfExists('home_skills');
        Schema::dropIfExists('home_counters');
        Schema::dropIfExists('home_services');
        Schema::dropIfExists('home_heroes');
        Schema::dropIfExists('home_statics');
    }
};
