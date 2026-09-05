<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 50);
            $table->string('path', 1024);
            $table->char('path_hash', 64)->unique();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100);
            $table->string('extension', 20);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('sha256', 64)->index();
            $table->string('visibility', 20)->default('public');
            $table->string('status', 20)->default('processing');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attachment_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('attachment_id')->constrained()->cascadeOnDelete();
            $table->string('variant', 32);
            $table->string('disk', 50);
            $table->string('path', 1024);
            $table->char('path_hash', 64)->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
            $table->unique(['attachment_id', 'variant']);
        });

        Schema::create('attachables', function (Blueprint $table) {
            $table->foreignUlid('attachment_id')->constrained()->cascadeOnDelete();
            $table->string('attachable_type', 100);
            $table->ulid('attachable_id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->index(['attachable_type', 'attachable_id']);
            $table->unique(['attachment_id', 'attachable_type', 'attachable_id'], 'attachables_identity_unique');
        });

        Schema::create('external_images', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('url', 2048);
            $table->string('host', 253);
            $table->foreignUlid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('video_embeds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('provider', 20);
            $table->string('video_id', 128);
            $table->string('original_url', 2048);
            $table->string('title_snapshot', 200)->nullable();
            $table->foreignUlid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['provider', 'video_id']);
        });

        Schema::create('embeddables', function (Blueprint $table) {
            $table->foreignUlid('video_embed_id')->constrained()->cascadeOnDelete();
            $table->string('embeddable_type', 100);
            $table->ulid('embeddable_id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->index(['embeddable_type', 'embeddable_id']);
            $table->unique(['video_embed_id', 'embeddable_type', 'embeddable_id'], 'embeddables_identity_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUlid('avatar_attachment_id')->nullable()->after('website_url')->constrained('attachments')->nullOnDelete();
        });
        Schema::table('characters', function (Blueprint $table) {
            $table->foreignUlid('avatar_attachment_id')->nullable()->after('profile_text')->constrained('attachments')->nullOnDelete();
        });
        Schema::table('ffxi_face_types', function (Blueprint $table) {
            $table->foreignUlid('avatar_attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ffxi_face_types', fn (Blueprint $table) => $table->dropConstrainedForeignId('avatar_attachment_id'));
        Schema::table('characters', fn (Blueprint $table) => $table->dropConstrainedForeignId('avatar_attachment_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('avatar_attachment_id'));
        Schema::dropIfExists('embeddables');
        Schema::dropIfExists('video_embeds');
        Schema::dropIfExists('external_images');
        Schema::dropIfExists('attachables');
        Schema::dropIfExists('attachment_variants');
        Schema::dropIfExists('attachments');
    }
};
