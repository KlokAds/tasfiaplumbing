<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_categories')) {
            Schema::create('service_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('intro')->nullable();
                $table->longText('description')->nullable();
                $table->string('image')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('meta_title')->nullable();
                $table->text('meta_desc')->nullable();
                $table->string('canonical')->nullable();
                $table->boolean('noindex')->default(false);
                $table->timestamp('content_updated_at')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'sort_order']);
            });
        }

        if (Schema::hasTable('service_details')) {
            Schema::table('service_details', function (Blueprint $table) {
                if (!Schema::hasColumn('service_details', 'category_id')) {
                    $table->foreignId('category_id')->nullable()->constrained('service_categories')->nullOnDelete();
                }
                if (!Schema::hasColumn('service_details', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (!Schema::hasColumn('service_details', 'published_at')) {
                    $table->timestamp('published_at')->nullable();
                }
                if (!Schema::hasColumn('service_details', 'canonical')) {
                    $table->string('canonical')->nullable();
                }
                if (!Schema::hasColumn('service_details', 'og_image')) {
                    $table->string('og_image')->nullable();
                }
                if (!Schema::hasColumn('service_details', 'noindex')) {
                    $table->boolean('noindex')->default(false);
                }
                if (!Schema::hasColumn('service_details', 'content_updated_at')) {
                    $table->timestamp('content_updated_at')->nullable();
                }
                if (!Schema::hasColumn('service_details', 'response_time')) {
                    $table->string('response_time')->nullable();
                }
                if (!Schema::hasColumn('service_details', 'warranty')) {
                    $table->string('warranty')->nullable();
                }
            });
        }

        if (!Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('region')->nullable();
                $table->text('intro')->nullable();
                $table->longText('description')->nullable();
                $table->json('property_types')->nullable();
                $table->json('nearby_areas')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('image')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->string('meta_title')->nullable();
                $table->text('meta_desc')->nullable();
                $table->string('canonical')->nullable();
                $table->boolean('noindex')->default(false);
                $table->timestamp('content_updated_at')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'is_featured', 'sort_order']);
            });
        }

        // Service x Location combo pages. Unpublished by default: a combo only goes
        // live once it has unique local content, otherwise it is a thin doorway page.
        if (!Schema::hasTable('service_location')) {
            Schema::create('service_location', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_id')->constrained('service_details')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
                $table->text('intro')->nullable();
                $table->text('local_proof')->nullable();
                $table->boolean('is_published')->default(false);
                $table->string('meta_title')->nullable();
                $table->text('meta_desc')->nullable();
                $table->timestamp('content_updated_at')->nullable();
                $table->timestamps();

                $table->unique(['service_id', 'location_id']);
                $table->index('is_published');
            });
        }

        if (!Schema::hasTable('faqs')) {
            Schema::create('faqs', function (Blueprint $table) {
                $table->id();
                $table->text('question');
                $table->text('answer');
                $table->nullableMorphs('faqable');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('redirects')) {
            Schema::create('redirects', function (Blueprint $table) {
                $table->id();
                $table->string('from_path', 512)->unique();
                $table->string('to_path', 512)->nullable();
                $table->unsignedSmallInteger('code')->default(301);
                $table->string('source', 32)->default('manual');
                $table->unsignedBigInteger('hit_count')->default(0);
                $table->timestamp('last_hit_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('service_location');
        Schema::dropIfExists('locations');

        if (Schema::hasTable('service_details')) {
            Schema::table('service_details', function (Blueprint $table) {
                if (Schema::hasColumn('service_details', 'category_id')) {
                    $table->dropConstrainedForeignId('category_id');
                }
                foreach (['is_active', 'published_at', 'canonical', 'og_image', 'noindex', 'content_updated_at', 'response_time', 'warranty'] as $col) {
                    if (Schema::hasColumn('service_details', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('service_categories');
    }
};
