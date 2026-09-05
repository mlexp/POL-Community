<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_initializations', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->timestamp('initialized_at');
            $table->foreignUlid('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_initializations');
    }
};
