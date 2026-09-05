<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title', 200);
            $table->string('summary', 500)->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('visibility', 20)->default('public');
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignUlid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'visibility', 'published_at']);
            if (DB::getDriverName() === 'mysql') {
                $table->fullText(['title', 'summary']);
            }
        });

        Schema::create('feed_sources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->string('url', 2048);
            $table->char('url_hash', 64)->unique();
            $table->string('visibility', 20)->default('public');
            $table->boolean('is_enabled')->default(false);
            $table->unsignedSmallInteger('fetch_interval_minutes')->default(60);
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('feed_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('feed_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 512);
            $table->char('external_id_hash', 64);
            $table->string('title', 500);
            $table->string('url', 2048);
            $table->text('summary_html')->nullable();
            $table->string('author', 200)->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('fetched_at');
            $table->unique(['feed_source_id', 'external_id_hash'], 'feed_items_source_external_unique');
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value');
            $table->boolean('is_public')->default(false);
            $table->foreignUlid('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('site_assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('slot', 50)->unique();
            $table->foreignUlid('attachment_id')->constrained()->restrictOnDelete();
            $table->string('alt_text', 255)->nullable();
            $table->foreignUlid('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->foreignUlid('image_attachment_id')->constrained('attachments')->restrictOnDelete();
            $table->string('destination_url', 2048);
            $table->string('alt_text');
            $table->string('placement', 30);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('rel_sponsored')->default(false);
            $table->timestamps();
            $table->index(['placement', 'is_enabled', 'sort_order']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('site_assets');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('feed_items');
        Schema::dropIfExists('feed_sources');
        Schema::dropIfExists('announcements');
    }
};
