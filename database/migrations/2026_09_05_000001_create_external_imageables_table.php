<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_imageables', function (Blueprint $table): void {
            $table->foreignUlid('external_image_id')->constrained()->cascadeOnDelete();
            $table->string('imageable_type', 100);
            $table->ulid('imageable_id');
            $table->unique(['external_image_id', 'imageable_type', 'imageable_id'], 'external_imageables_identity_unique');
            $table->index(['imageable_type', 'imageable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_imageables');
    }
};
