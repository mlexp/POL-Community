<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ImageUpload
{
    public function store(UploadedFile $file, User $user, string $visibility = 'private'): Attachment
    {
        $limit = min((int) app(SiteSettings::class)->get('upload.image_max_bytes', 1048576), (int) config('content.image_hard_max_bytes'));
        if (! $file->isValid() || $file->getSize() > $limit || ! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $this->invalid();
        }
        $bytes = file_get_contents($file->getPathname());
        if ($bytes === false) {
            $this->invalid();
        }
        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info[0] < 1 || $info[1] < 1 || max($info[0], $info[1]) > config('content.image_max_dimension') || config('content.image_max_pixels') < $info[0] * $info[1]) {
            $this->invalid();
        }
        if (! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages(['image' => '画像処理拡張GDが未導入です。運用者に連絡してください。']);
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            $this->invalid();
        }
        $directory = 'users/'.$user->id.'/images/'.Str::ulid();
        $paths = [];
        try {
            $original = $this->encode($image, $info[0], $info[1]);
            $ratio = min(1, (int) config('content.thumbnail_dimension') / max($info[0], $info[1]));
            $width = max(1, (int) round($info[0] * $ratio));
            $height = max(1, (int) round($info[1] * $ratio));
            $thumbnail = $this->encode($image, $width, $height);
            foreach (['original' => $original, 'thumbnail' => $thumbnail] as $variant => $data) {
                $path = $directory.'/'.$variant.'.webp';
                $paths[] = $path;
                if (! Storage::disk('local')->put($path, $data)) {
                    throw new RuntimeException('画像を保存できませんでした。');
                }
            }

            return DB::transaction(function () use ($file, $user, $visibility, $directory, $original, $thumbnail, $info, $width, $height): Attachment {
                $path = $directory.'/original.webp';
                $attachment = Attachment::create(['uploaded_by_user_id' => $user->id, 'disk' => 'local', 'path' => $path, 'path_hash' => hash('sha256', $path), 'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'mime_type' => 'image/webp', 'extension' => 'webp', 'size_bytes' => strlen($original), 'width' => $info[0], 'height' => $info[1], 'sha256' => hash('sha256', $original), 'visibility' => $visibility, 'status' => 'ready']);
                $thumbPath = $directory.'/thumbnail.webp';
                DB::table('attachment_variants')->insert(['id' => (string) Str::ulid(), 'attachment_id' => $attachment->id, 'variant' => 'thumbnail', 'disk' => 'local', 'path' => $thumbPath, 'path_hash' => hash('sha256', $thumbPath), 'mime_type' => 'image/webp', 'size_bytes' => strlen($thumbnail), 'width' => $width, 'height' => $height, 'created_at' => now(), 'updated_at' => now()]);

                return $attachment;
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        } finally {
            imagedestroy($image);
        }
    }

    private function encode(\GdImage $source, int $width, int $height): string
    {
        if ($width < 1 || $height < 1) {
            throw new RuntimeException('画像寸法が不正です。');
        }
        $clean = imagecreatetruecolor($width, $height);
        if ($clean === false) {
            throw new RuntimeException('画像処理に失敗しました。');
        }
        imagealphablending($clean, false);
        imagesavealpha($clean, true);
        imagecopyresampled($clean, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        ob_start();
        try {
            if (! imagewebp($clean, null, 85)) {
                throw new RuntimeException('画像変換に失敗しました。');
            }
            $bytes = ob_get_contents();
            if ($bytes === false || $bytes === '') {
                throw new RuntimeException('画像変換に失敗しました。');
            }

            return $bytes;
        } finally {
            ob_end_clean();
            imagedestroy($clean);
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['image' => 'JPEG・PNG・WebP画像を指定し、サイズ・寸法上限を確認してください。']);
    }
}
