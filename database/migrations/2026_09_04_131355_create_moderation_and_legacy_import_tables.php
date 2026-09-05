<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('reported_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reportable_type', 100);
            $table->ulid('reportable_id');
            $table->string('reason', 100);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();
            $table->index(['reportable_type', 'reportable_id']);
        });

        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('subject_type', 100);
            $table->string('subject_id', 64);
            $table->string('action', 50);
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('legacy_import_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('source_name', 100);
            $table->string('status', 20);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('stats_json')->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('legacy_id_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('import_run_id')->constrained('legacy_import_runs')->cascadeOnDelete();
            $table->string('source_table', 64);
            $table->string('legacy_id', 64);
            $table->string('target_type', 100);
            $table->ulid('target_id');
            $table->char('source_checksum', 64)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['source_table', 'legacy_id', 'target_type'], 'legacy_mappings_identity_unique');
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('legacy_import_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('import_run_id')->constrained('legacy_import_runs')->cascadeOnDelete();
            $table->string('source_table', 64);
            $table->string('legacy_id', 64)->nullable();
            $table->string('severity', 20);
            $table->string('code', 100);
            $table->text('message');
            $table->json('context_json')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['import_run_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_import_issues');
        Schema::dropIfExists('legacy_id_mappings');
        Schema::dropIfExists('legacy_import_runs');
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('content_reports');
    }
};
