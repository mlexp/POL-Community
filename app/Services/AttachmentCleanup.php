<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AttachmentCleanup
{
    public function deleteIfUnused(?string $id): bool
    {
        if ($id === null || $this->inUse($id)) {
            return false;
        }
        $attachment = DB::table('attachments')->where('id', $id)->whereNull('deleted_at')->first();
        if ($attachment === null) {
            return false;
        }
        $paths = DB::table('attachment_variants')->where('attachment_id', $id)->pluck('path')->push($attachment->path)->all();
        Storage::disk($attachment->disk)->delete($paths);
        DB::table('attachments')->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()]);

        return true;
    }

    public function inUse(string $id): bool
    {
        return DB::table('site_assets')->where('attachment_id', $id)->exists() || DB::table('banners')->where('image_attachment_id', $id)->exists()
            || DB::table('users')->where('avatar_attachment_id', $id)->exists() || DB::table('characters')->where('avatar_attachment_id', $id)->exists()
            || DB::table('ffxi_face_types')->where('avatar_attachment_id', $id)->exists() || DB::table('attachables')->where('attachment_id', $id)->exists();
    }
}
