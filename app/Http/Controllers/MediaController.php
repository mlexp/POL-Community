<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\ContentMedia;
use App\Services\ImageUpload;
use App\Support\ContentAccess;
use App\Support\MediaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function edit(string $type, string $id, ContentMedia $media): View
    {
        Gate::authorize('update', $media->parent($type, $id));

        return view('media.edit', ['type' => $type, 'id' => $id, ...$media->listing($type, $id)]);
    }

    public function store(Request $request, string $type, string $id, ContentMedia $media, ImageUpload $upload, MediaUrl $urls): RedirectResponse
    {
        $parent = $media->parent($type, $id);
        Gate::authorize('update', $parent);
        abort_unless(app(ContentAccess::class)->member($request->user()), 403);
        $data = $request->validate(['kind' => ['required', 'in:image,external,video'], 'image' => ['required_if:kind,image', 'file'], 'url' => ['required_unless:kind,image', 'nullable', 'string', 'max:2048']]);
        if ($data['kind'] === 'image') {
            $file = $request->file('image');
            if (! $file instanceof UploadedFile) {
                abort(422);
            }
            $attachment = $upload->store($file, $request->user());
            try {
                DB::table('attachables')->insert(['attachment_id' => $attachment->id, 'attachable_type' => $type, 'attachable_id' => $id, 'sort_order' => 0]);
            } catch (\Throwable $e) {
                $attachment->delete();
                throw $e;
            }
        } elseif ($data['kind'] === 'external') {
            if (! $urls->https($data['url'])) {
                throw ValidationException::withMessages(['url' => 'HTTPSの画像URLを指定してください。']);
            }
            DB::transaction(function () use ($data, $request, $type, $id): void {
                $imageId = (string) Str::ulid();
                DB::table('external_images')->insert(['id' => $imageId, 'url' => $data['url'], 'host' => parse_url($data['url'], PHP_URL_HOST), 'created_by_user_id' => $request->user()->id, 'created_at' => now()]);
                DB::table('external_imageables')->insert(['external_image_id' => $imageId, 'imageable_type' => $type, 'imageable_id' => $id]);
            });
        } else {
            $video = $urls->video($data['url']);
            DB::transaction(function () use ($data, $video, $request, $type, $id): void {
                DB::table('video_embeds')->insertOrIgnore(['id' => (string) Str::ulid(), 'provider' => $video['provider'], 'video_id' => $video['video_id'], 'original_url' => $data['url'], 'created_by_user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
                $videoId = DB::table('video_embeds')->where('provider', $video['provider'])->where('video_id', $video['video_id'])->value('id');
                DB::table('embeddables')->insertOrIgnore(['video_embed_id' => $videoId, 'embeddable_type' => $type, 'embeddable_id' => $id, 'sort_order' => 0]);
            });
        }

        return back()->with('status', 'メディアを追加しました。');
    }

    public function destroy(string $type, string $id, string $kind, string $mediaId, ContentMedia $media): RedirectResponse
    {
        Gate::authorize('update', $media->parent($type, $id));
        [$table, $prefix, $key] = match ($kind) {
            'image' => ['attachables', 'attachable', 'attachment_id'],
            'external' => ['external_imageables', 'imageable', 'external_image_id'],
            'video' => ['embeddables', 'embeddable', 'video_embed_id'],
            default => abort(404),
        };
        DB::table($table)->where($prefix.'_type', $type)->where($prefix.'_id', $id)->where($key, $mediaId)->delete();

        return back()->with('status', 'メディアを取り外しました。');
    }

    public function show(Attachment $attachment, string $variant = 'original'): StreamedResponse
    {
        Gate::authorize('view', $attachment);
        abort_unless(in_array($variant, ['original', 'thumbnail'], true), 404);
        $path = $attachment->path;
        if ($variant === 'thumbnail') {
            $path = DB::table('attachment_variants')->where('attachment_id', $attachment->id)->where('variant', 'thumbnail')->firstOrFail()->path;
        }
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $attachment->id.'.webp', ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
