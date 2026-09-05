<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diaries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('character_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->json('body_json')->nullable();
            $table->mediumText('body_html');
            $table->string('excerpt', 500)->nullable();
            $table->string('visibility', 20)->default('public');
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('comments_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status', 'published_at']);
            $table->index(['status', 'visibility', 'published_at']);
            if (DB::getDriverName() === 'mysql') {
                $table->fullText(['title', 'body_html']);
            }
        });

        Schema::create('diary_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('diary_category', function (Blueprint $table) {
            $table->foreignUlid('diary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diary_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['diary_id', 'diary_category_id']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('diary_tag', function (Blueprint $table) {
            $table->foreignUlid('diary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['diary_id', 'tag_id']);
        });

        Schema::create('diary_comments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('diary_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('parent_id')->nullable()->constrained('diary_comments')->nullOnDelete();
            $table->string('author_name_snapshot', 100)->nullable();
            $table->text('body_html');
            $table->string('status', 20)->default('visible');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['diary_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_comments');
        Schema::dropIfExists('diary_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('diary_category');
        Schema::dropIfExists('diary_categories');
        Schema::dropIfExists('diaries');
    }
};
