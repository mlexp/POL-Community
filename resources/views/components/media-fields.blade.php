<fieldset class="space-y-4 rounded border p-4">
    <legend class="px-2 font-semibold">画像・動画を添付（任意）</legend>
    <label class="block">
        <span class="mb-2 block text-sm">画像ファイル</span>
        <input class="block w-full cursor-pointer rounded border bg-white text-sm file:mr-4 file:cursor-pointer file:border-0 file:bg-zinc-800 file:px-4 file:py-2 file:text-white hover:file:bg-zinc-700" type="file" name="media_image" accept="image/jpeg,image/png,image/webp">
    </label>
    <label class="block text-sm">外部画像のHTTPS URL<input class="mt-1 block w-full rounded border p-2" type="url" name="media_external_url" value="{{ old('media_external_url') }}" placeholder="https://example.com/image.jpg"></label>
    <label class="block text-sm">YouTube／ニコニコ動画のURL<input class="mt-1 block w-full rounded border p-2" type="url" name="media_video_url" value="{{ old('media_video_url') }}" placeholder="https://www.youtube.com/watch?v=..."></label>
</fieldset>
