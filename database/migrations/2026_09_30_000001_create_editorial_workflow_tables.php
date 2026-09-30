<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Author profile (shown on articles for E-E-A-T).
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'job_title')) {
                $table->string('job_title')->nullable();
            }
            if (!Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable();
            }
            if (!Schema::hasColumn('users', 'social_url')) {
                $table->string('social_url')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
        });

        // Article publishing workflow: draft -> pending (review) -> published.
        Schema::table('blog_details', function (Blueprint $table) {
            if (!Schema::hasColumn('blog_details', 'status')) {
                $table->string('status', 16)->default('draft')->index();
            }
            if (!Schema::hasColumn('blog_details', 'author_id')) {
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('blog_details', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable();
            }
            if (!Schema::hasColumn('blog_details', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('blog_details', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }
            if (!Schema::hasColumn('blog_details', 'review_note')) {
                $table->text('review_note')->nullable();
            }
        });
        DB::table('blog_details')->where('is_active', true)->update(['status' => 'published']);
        DB::table('blog_details')->where('is_active', false)->update(['status' => 'draft']);

        // Changes to an already-published article made by someone who cannot publish.
        // The live article stays untouched until a publisher approves.
        if (!Schema::hasTable('article_revisions')) {
            Schema::create('article_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('article_id')->constrained('blog_details')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('payload');
                $table->string('status', 16)->default('pending')->index();
                $table->text('note')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        // Autosaved form state so a power cut or closed tab never loses writing.
        if (!Schema::hasTable('drafts')) {
            Schema::create('drafts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 32);
                $table->unsignedBigInteger('record_id')->default(0);
                $table->json('payload');
                $table->timestamps();

                $table->unique(['user_id', 'type', 'record_id']);
            });
        }

        // SEO for fixed pages (home, about, contact, listing pages). One place instead of
        // meta fields spread over home_statics, about_contents, bread_cumbs and contact_contents.
        if (!Schema::hasTable('page_seo')) {
            Schema::create('page_seo', function (Blueprint $table) {
                $table->id();
                $table->string('key', 64)->unique();
                $table->string('meta_title')->nullable();
                $table->text('meta_desc')->nullable();
                $table->string('focus_keyword')->nullable();
                $table->string('og_image')->nullable();
                $table->string('canonical')->nullable();
                $table->boolean('noindex')->default(false);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });

            $legacy = [
                'home' => ['home_statics', 'meta_title', 'meta_description'],
                'about' => ['about_contents', 'meta_title', 'meta_description'],
                'services' => ['bread_cumbs', 's_meta_title', 's_meta_desc'],
                'projects' => ['bread_cumbs', 'p_meta_title', 'p_meta_desc'],
                'testimonials' => ['bread_cumbs', 'f_meta_title', 'f_meta_desc'],
                'articles' => ['bread_cumbs', 'b_meta_title', 'b_meta_desc'],
                'contact' => ['contact_contents', 'meta_title', 'meta_desc'],
            ];
            foreach ($legacy as $key => [$table, $titleCol, $descCol]) {
                $row = Schema::hasTable($table) && Schema::hasColumn($table, $titleCol) ? DB::table($table)->first() : null;
                DB::table('page_seo')->insert([
                    'key' => $key,
                    'meta_title' => $row?->{$titleCol} ?: null,
                    'meta_desc' => ($row && Schema::hasColumn($table, $descCol)) ? ($row->{$descCol} ?: null) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_seo');
        Schema::dropIfExists('drafts');
        Schema::dropIfExists('article_revisions');

        Schema::table('blog_details', function (Blueprint $table) {
            foreach (['author_id', 'reviewed_by'] as $fk) {
                if (Schema::hasColumn('blog_details', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }
            foreach (['status', 'submitted_at', 'reviewed_at', 'review_note'] as $col) {
                if (Schema::hasColumn('blog_details', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['job_title', 'bio', 'social_url', 'last_login_at'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
