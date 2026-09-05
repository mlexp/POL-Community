<?php

namespace App\Services;

use App\Models\CommunityPost;
use App\Models\Diary;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\DB;

class ContentMedia
{
    public function parent(string $type, string $id): Diary|CommunityPost
    {
        return match ($type) {
            'diary' => Diary::findOrFail($id),
            'community_post' => CommunityPost::findOrFail($id),
            default => abort(404),
        };
    }

    /** @return array<string, mixed> */
    public function listing(string $type, string $id): array
    {
        $images = DB::table('attachments')->join('attachables', 'attachments.id', '=', 'attachables.attachment_id')->where('attachable_type', $type)->where('attachable_id', $id)->whereNull('attachments.deleted_at')->where('attachments.status', 'ready')->orderBy('sort_order')->get(['attachments.*']);
        $external = DB::table('external_images')->join('external_imageables', 'external_images.id', '=', 'external_imageables.external_image_id')->where('imageable_type', $type)->where('imageable_id', $id)->get(['external_images.*'])->filter(fn ($row) => app(MediaUrl::class)->https($row->url));
        $videos = DB::table('video_embeds')->join('embeddables', 'video_embeds.id', '=', 'embeddables.video_embed_id')->where('embeddable_type', $type)->where('embeddable_id', $id)->orderBy('sort_order')->get(['video_embeds.*']);

        return compact('images', 'external', 'videos');
    }
}
