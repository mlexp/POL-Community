<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('service', 32);
            $table->string('label', 50)->nullable();
            $table->string('profile_url', 2048);
            $table->char('profile_url_hash', 64);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'service', 'profile_url_hash'], 'social_links_identity_unique');
        });

        Schema::create('games', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->autoIncrement();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->boolean('is_enabled')->default(true);
        });

        foreach (['ffxi_worlds', 'ffxi_nations', 'ffxi_races', 'ffxi_jobs', 'ffxi_crafts'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->unsignedSmallInteger('id')->autoIncrement();
                $table->string('code', 32)->unique();
                $table->string('name_ja', 100);
                $table->string('name_en', 100)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
            });
        }

        Schema::create('ffxi_face_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->autoIncrement();
            $table->unsignedSmallInteger('race_id');
            $table->foreign('race_id')->references('id')->on('ffxi_races')->cascadeOnDelete();
            $table->string('face_code', 32);
            $table->string('name_ja', 100);
            $table->string('name_en', 100)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unique(['race_id', 'face_code']);
        });

        Schema::create('characters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('game_id');
            $table->foreign('game_id')->references('id')->on('games')->restrictOnDelete();
            $table->string('name', 100);
            $table->boolean('is_primary')->default(false);
            $table->text('profile_text')->nullable();
            $table->string('visibility', 20)->default('public');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'game_id', 'is_primary']);
        });

        Schema::create('ffxi_profiles', function (Blueprint $table) {
            $table->foreignUlid('character_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('world_id')->nullable();
            $table->foreign('world_id')->references('id')->on('ffxi_worlds')->nullOnDelete();
            $table->unsignedSmallInteger('nation_id')->nullable();
            $table->foreign('nation_id')->references('id')->on('ffxi_nations')->nullOnDelete();
            $table->unsignedTinyInteger('rank')->nullable();
            $table->unsignedSmallInteger('race_id')->nullable();
            $table->foreign('race_id')->references('id')->on('ffxi_races')->nullOnDelete();
            $table->unsignedSmallInteger('face_type_id')->nullable();
            $table->foreign('face_type_id')->references('id')->on('ffxi_face_types')->nullOnDelete();
            $table->unsignedSmallInteger('main_job_id')->nullable();
            $table->foreign('main_job_id')->references('id')->on('ffxi_jobs')->nullOnDelete();
            $table->unsignedSmallInteger('support_job_id')->nullable();
            $table->foreign('support_job_id')->references('id')->on('ffxi_jobs')->nullOnDelete();
            $table->string('pol_handle', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('ffxi_character_job_levels', function (Blueprint $table) {
            $table->foreignUlid('character_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('job_id');
            $table->foreign('job_id')->references('id')->on('ffxi_jobs')->restrictOnDelete();
            $table->unsignedTinyInteger('level');
            $table->timestamp('updated_at')->nullable();
            $table->primary(['character_id', 'job_id']);
        });

        Schema::create('ffxi_character_craft_levels', function (Blueprint $table) {
            $table->foreignUlid('character_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('craft_id');
            $table->foreign('craft_id')->references('id')->on('ffxi_crafts')->restrictOnDelete();
            $table->unsignedSmallInteger('level');
            $table->timestamp('updated_at')->nullable();
            $table->primary(['character_id', 'craft_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ffxi_character_craft_levels');
        Schema::dropIfExists('ffxi_character_job_levels');
        Schema::dropIfExists('ffxi_profiles');
        Schema::dropIfExists('characters');
        Schema::dropIfExists('ffxi_face_types');
        foreach (['ffxi_crafts', 'ffxi_jobs', 'ffxi_races', 'ffxi_nations', 'ffxi_worlds'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::dropIfExists('games');
        Schema::dropIfExists('social_links');
    }
};
