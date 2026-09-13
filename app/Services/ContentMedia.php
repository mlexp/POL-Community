<?php

namespace App\Services;

use App\Models\CommunityPost;
use App\Models\Diary;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContentMedia
{
    /** @return array<string, mixed> */
    public function validatePending(Request $request): array
    {
        $data = $request->validate([
            'media_image' => ['nullable', 'file'],
            'media_external_url' => ['nullable', 'string', 'max:2048'],
            'media_video_url' => ['nullable', 'string', 'max:2048'],
        ]);
        if (! empty($data['media_external_url']) && ! app(MediaUrl::class)->https($data['media_external_url'])) {
            throw ValidationException::withMessages(['media_external_url' => 'HTTPSの画像URLを指定してください。']);
        }
        if (! empty($data['media_video_url'])) {
            app(MediaUrl::class)->video($data['media_video_url']);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    public function attachPending(array $data, User $user, string $type, string $id, string $visibility, ImageUpload $upload): void
    {
        $file = $data['media_image'] ?? null;
        if ($file instanceof UploadedFile) {
            $attachment = $upload->store($file, $user, $visibility);
            DB::table('attachables')->insert(['attachment_id' => $attachment->id, 'attachable_type' => $type, 'attachable_id' => $id, 'sort_order' => 0]);
        }
        if (is_string($data['media_external_url'] ?? null) && $data['media_external_url'] !== '') {
            $imageId = (string) Str::ulid();
            DB::table('external_images')->insert(['id' => $imageId, 'url' => $data['media_external_url'], 'host' => parse_url($data['media_external_url'], PHP_URL_HOST), 'created_by_user_id' => $user->id, 'created_at' => now()]);
            DB::table('external_imageables')->insert(['external_image_id' => $imageId, 'imageable_type' => $type, 'imageable_id' => $id]);
        }
        if (is_string($data['media_video_url'] ?? null) && $data['media_video_url'] !== '') {
            $video = app(MediaUrl::class)->video($data['media_video_url']);
            DB::table('video_embeds')->insertOrIgnore(['id' => (string) Str::ulid(), 'provider' => $video['provider'], 'video_id' => $video['video_id'], 'original_url' => $data['media_video_url'], 'created_by_user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            $videoId = DB::table('video_embeds')->where('provider', $video['provider'])->where('video_id', $video['video_id'])->value('id');
            DB::table('embeddables')->insertOrIgnore(['video_embed_id' => $videoId, 'embeddable_type' => $type, 'embeddable_id' => $id, 'sort_order' => 0]);
        }
    }

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
