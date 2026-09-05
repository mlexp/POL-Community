<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageUpload;
use App\Support\AuditLogger;
use App\Support\MediaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContentAdminController extends Controller
{
    public function categories(): View
    {
        return view('admin.community-categories', ['categories' => DB::table('community_categories')->orderBy('sort_order')->get()]);
    }

    public function saveCategory(Request $request, AuditLogger $audit, ?int $category = null): RedirectResponse
    {
        if ($category !== null) {
            DB::table('community_categories')->where('id', $category)->firstOrFail();
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('community_categories', 'slug')->ignore($category)], 'description' => ['nullable', 'string', 'max:500'], 'sort_order' => ['required', 'integer', 'between:0,65535'], 'is_active' => ['required', 'boolean']]);
        if ($category === null) {
            $category = DB::table('community_categories')->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('community_categories')->where('id', $category)->update([...$data, 'updated_at' => now()]);
        }
        $audit->log($request, 'community_category.saved', 'community_category', null, ['id' => $category, ...$data]);

        return back()->with('status', 'カテゴリを保存しました。');
    }

    public function announcements(): View
    {
        return view('admin.announcements', ['announcements' => DB::table('announcements')->whereNull('deleted_at')->latest('created_at')->paginate(20)]);
    }

    public function saveAnnouncement(Request $request, AuditLogger $audit, ?string $id = null): RedirectResponse
    {
        if ($id !== null) {
            DB::table('announcements')->where('id', $id)->whereNull('deleted_at')->firstOrFail();
        }
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'summary' => ['nullable', 'string', 'max:500'], 'url' => ['nullable', 'string', 'max:2048'], 'visibility' => ['required', 'in:public,members,private'], 'status' => ['required', 'in:draft,published,archived'], 'published_at' => ['nullable', 'date']]);
        if (! empty($data['url']) && ! app(MediaUrl::class)->https($data['url'])) {
            throw ValidationException::withMessages(['url' => 'HTTPS URLを入力してください。']);
        }
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }
        if ($id === null) {
            $id = (string) Str::ulid();
            DB::table('announcements')->insert(['id' => $id, ...$data, 'created_by_user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('announcements')->where('id', $id)->update([...$data, 'updated_at' => now()]);
        }
        $audit->log($request, 'announcement.saved', 'announcement', null, ['id' => $id, 'status' => $data['status']]);

        return back()->with('status', '更新情報を保存しました。');
    }

    public function deleteAnnouncement(Request $request, string $id, AuditLogger $audit): RedirectResponse
    {
        DB::table('announcements')->where('id', $id)->whereNull('deleted_at')->firstOrFail();
        DB::table('announcements')->where('id', $id)->update(['deleted_at' => now()]);
        $audit->log($request, 'announcement.deleted', 'announcement', null, ['id' => $id]);

        return back()->with('status', '更新情報を削除しました。');
    }

    public function images(): View
    {
        return view('admin.images', ['images' => DB::table('attachments')->whereNull('deleted_at')->where('visibility', 'public')->where('status', 'ready')->latest('created_at')->paginate(20)]);
    }

    public function upload(Request $request, ImageUpload $upload, AuditLogger $audit): RedirectResponse
    {
        $request->validate(['image' => ['required', 'file'], 'slot' => ['nullable', 'in:header'], 'alt_text' => ['nullable', 'string', 'max:255']]);
        $file = $request->file('image');
        if (! $file instanceof UploadedFile) {
            abort(422);
        }
        $image = $upload->store($file, $request->user(), 'public');
        if ($request->input('slot') === 'header') {
            DB::table('site_assets')->updateOrInsert(['slot' => 'header'], ['id' => (string) Str::ulid(), 'attachment_id' => $image->id, 'alt_text' => $request->input('alt_text'), 'updated_by_user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $audit->log($request, 'site_image.uploaded', $image);

        return back()->with('status', '画像をアップロードしました。');
    }
}
