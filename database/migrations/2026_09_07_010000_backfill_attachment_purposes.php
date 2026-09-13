<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attachments')->whereIn('id', DB::table('banners')->select('image_attachment_id'))->whereNull('purpose')->update(['purpose' => 'banner']);
        DB::table('attachments')->whereIn('id', DB::table('site_assets')->select('attachment_id'))->update(['purpose' => 'header']);
    }

    public function down(): void
    {
        // Values assigned later by administrators cannot safely be distinguished from backfilled values.
    }
};
