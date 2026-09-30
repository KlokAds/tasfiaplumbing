<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Central price list: service pages, /pricing and schema all read from here.
        if (!Schema::hasTable('prices')) {
            Schema::create('prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_id')->nullable()->constrained('service_details')->nullOnDelete();
                $table->string('item');
                $table->unsignedInteger('price_from');
                $table->unsignedInteger('price_to')->nullable();
                $table->string('unit')->nullable();
                $table->string('currency', 3)->default('SGD');
                $table->string('gst_note')->nullable();
                $table->boolean('is_featured')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('last_reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['service_id', 'is_active', 'sort_order']);
            });
        }

        if (!Schema::hasTable('not_found_logs')) {
            Schema::create('not_found_logs', function (Blueprint $table) {
                $table->id();
                $table->string('path', 512)->unique();
                $table->unsignedBigInteger('hits')->default(1);
                $table->string('last_referer', 512)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->boolean('is_resolved')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('deploy_logs')) {
            Schema::create('deploy_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 32);
                $table->string('status', 16)->default('running');
                $table->string('commit_before', 64)->nullable();
                $table->string('commit_after', 64)->nullable();
                $table->longText('output')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('service_details')) {
            Schema::table('service_details', function (Blueprint $table) {
                if (!Schema::hasColumn('service_details', 'short_summary')) {
                    $table->text('short_summary')->nullable();
                }
                if (!Schema::hasColumn('service_details', 'focus_keyword')) {
                    $table->string('focus_keyword')->nullable();
                }
            });
        }

        if (Schema::hasTable('blog_details')) {
            Schema::table('blog_details', function (Blueprint $table) {
                if (!Schema::hasColumn('blog_details', 'primary_service_id')) {
                    $table->foreignId('primary_service_id')->nullable()->constrained('service_details')->nullOnDelete();
                }
                if (!Schema::hasColumn('blog_details', 'excerpt')) {
                    $table->text('excerpt')->nullable();
                }
                if (!Schema::hasColumn('blog_details', 'focus_keyword')) {
                    $table->string('focus_keyword')->nullable();
                }
                if (!Schema::hasColumn('blog_details', 'canonical')) {
                    $table->string('canonical')->nullable();
                }
                if (!Schema::hasColumn('blog_details', 'og_image')) {
                    $table->string('og_image')->nullable();
                }
                if (!Schema::hasColumn('blog_details', 'noindex')) {
                    $table->boolean('noindex')->default(false);
                }
                if (!Schema::hasColumn('blog_details', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (!Schema::hasColumn('blog_details', 'published_at')) {
                    $table->timestamp('published_at')->nullable();
                }
                if (!Schema::hasColumn('blog_details', 'content_updated_at')) {
                    $table->timestamp('content_updated_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('blog_details')) {
            Schema::table('blog_details', function (Blueprint $table) {
                if (Schema::hasColumn('blog_details', 'primary_service_id')) {
                    $table->dropConstrainedForeignId('primary_service_id');
                }
                foreach (['excerpt', 'focus_keyword', 'canonical', 'og_image', 'noindex', 'is_active', 'published_at', 'content_updated_at'] as $col) {
                    if (Schema::hasColumn('blog_details', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('service_details')) {
            Schema::table('service_details', function (Blueprint $table) {
                foreach (['short_summary', 'focus_keyword'] as $col) {
                    if (Schema::hasColumn('service_details', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('deploy_logs');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('not_found_logs');
        Schema::dropIfExists('prices');
    }
};
