<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('characters', 'short_message')) {
            Schema::table('characters', fn (Blueprint $table) => $table->string('short_message', 280)->nullable()->after('profile_text'));
        }
        if (! Schema::hasColumn('ffxi_nations', 'flag_path')) {
            Schema::table('ffxi_nations', fn (Blueprint $table) => $table->string('flag_path', 255)->nullable()->after('name_en'));
        }
        if (! Schema::hasColumn('ffxi_face_types', 'gender')) {
            // MySQL used the old composite unique index for the race_id foreign key.
            // Give that key its own index before replacing the unique constraint.
            if (DB::getDriverName() === 'mysql') {
                Schema::table('ffxi_face_types', fn (Blueprint $table) => $table->index('race_id', 'ffxi_face_types_race_id_index'));
            }
            Schema::table('ffxi_face_types', function (Blueprint $table) {
                $table->dropUnique(['race_id', 'face_code']);
                $table->string('gender', 10)->nullable()->after('race_id');
                $table->string('image_path', 255)->nullable()->after('name_en');
            });
        }
        if (! Schema::hasColumn('ffxi_profiles', 'gender')) {
            Schema::table('ffxi_profiles', fn (Blueprint $table) => $table->string('gender', 10)->nullable()->after('race_id'));
        }

        DB::table('ffxi_face_types')->whereIn('race_id', DB::table('ffxi_races')->whereIn('code', ['mithra'])->pluck('id'))->update(['gender' => 'female']);
        DB::table('ffxi_face_types')->whereNull('gender')->update(['gender' => 'male']);

        if (! collect(Schema::getIndexes('ffxi_face_types'))->contains(fn (array $index) => $index['name'] === 'ffxi_faces_identity_unique')) {
            Schema::table('ffxi_face_types', fn (Blueprint $table) => $table->unique(['race_id', 'gender', 'face_code'], 'ffxi_faces_identity_unique'));
        }
    }

    public function down(): void
    {
        Schema::table('ffxi_face_types', function (Blueprint $table) {
            $table->dropUnique('ffxi_faces_identity_unique');
            $table->dropColumn(['gender', 'image_path']);
            $table->unique(['race_id', 'face_code']);
        });
        Schema::table('ffxi_profiles', fn (Blueprint $table) => $table->dropColumn('gender'));
        Schema::table('ffxi_nations', fn (Blueprint $table) => $table->dropColumn('flag_path'));
        Schema::table('characters', fn (Blueprint $table) => $table->dropColumn('short_message'));
    }
};
