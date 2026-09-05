<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->autoIncrement();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->char('color', 7)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('community_threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedSmallInteger('category_id');
            $table->foreign('category_id')->references('id')->on('community_categories')->restrictOnDelete();
            $table->foreignUlid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->string('visibility', 20)->default('public');
            $table->string('status', 20)->default('open');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('last_posted_at');
            $table->unsignedInteger('post_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'visibility', 'is_pinned', 'last_posted_at'], 'community_threads_listing_index');
        });

        Schema::create('community_posts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('thread_id')->constrained('community_threads')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('parent_id')->nullable()->constrained('community_posts')->nullOnDelete();
            $table->string('author_name_snapshot', 100)->nullable();
            $table->mediumText('body_html');
            $table->string('status', 20)->default('visible');
            $table->string('legacy_contact_email', 254)->nullable();
            $table->string('legacy_access_log', 512)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['thread_id', 'status', 'created_at']);
            if (DB::getDriverName() === 'mysql') {
                $table->fullText('body_html');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_posts');
        Schema::dropIfExists('community_threads');
        Schema::dropIfExists('community_categories');
    }
};
