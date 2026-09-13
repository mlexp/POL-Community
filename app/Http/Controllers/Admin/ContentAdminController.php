<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AttachmentCleanup;
use App\Services\ImageUpload;
use App\Support\AuditLogger;
use App\Support\MediaUrl;
use App\Support\UpdateTimestamp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContentAdminController extends Controller
{
    public function content(Request $request): View
    {
        $data = $request->validate(['type' => ['nullable', Rule::in(['diary', 'thread', 'reply'])], 'title' => ['nullable', 'string', 'max:200'], 'author' => ['nullable', 'string', 'max:100'], 'created_from' => ['nullable', 'date'], 'created_to' => ['nullable', 'date'], 'updated_from' => ['nullable', 'date'], 'updated_to' => ['nullable', 'date'], 'sort' => ['nullable', Rule::in(['type', 'title', 'author', 'created_at', 'updated_at'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])]]);
        $apply = function ($query, string $titleColumn, string $authorColumn, string $datePrefix) use ($data) {
            return $query->when($data['title'] ?? null, fn ($q, $v) => $q->where($titleColumn, 'like', '%'.$v.'%'))->when($data['author'] ?? null, fn ($q, $v) => $q->where($authorColumn, 'like', '%'.$v.'%'))->when($data['created_from'] ?? null, fn ($q, $v) => $q->whereDate($datePrefix.'.created_at', '>=', $v))->when($data['created_to'] ?? null, fn ($q, $v) => $q->whereDate($datePrefix.'.created_at', '<=', $v))->when($data['updated_from'] ?? null, fn ($q, $v) => $q->whereDate($datePrefix.'.updated_at', '>=', $v))->when($data['updated_to'] ?? null, fn ($q, $v) => $q->whereDate($datePrefix.'.updated_at', '<=', $v));
        };
        $rows = collect();
        if (! isset($data['type']) || $data['type'] === 'diary') {
            $rows = $rows->concat($apply(DB::table('diaries')->join('users', 'diaries.user_id', '=', 'users.id')->whereNull('diaries.deleted_at'), 'diaries.title', 'users.display_name', 'diaries')->get(['diaries.id', DB::raw("'diary' as type"), 'diaries.title', 'users.display_name as author', 'diaries.created_at', 'diaries.updated_at']));
        }
        if (! isset($data['type']) || $data['type'] === 'thread') {
            $rows = $rows->concat($apply(DB::table('community_threads')->leftJoin('users', 'community_threads.created_by_user_id', '=', 'users.id')->whereNull('community_threads.deleted_at'), 'community_threads.title', 'users.display_name', 'community_threads')->get(['community_threads.id', DB::raw("'thread' as type"), 'community_threads.title', DB::raw("COALESCE(users.display_name, '削除済みユーザー') as author"), 'community_threads.created_at', 'community_threads.updated_at']));
        }
        if (! isset($data['type']) || $data['type'] === 'reply') {
            $replies = $apply(DB::table('community_posts')->join('community_threads', 'community_posts.thread_id', '=', 'community_threads.id')->leftJoin('users', 'community_posts.user_id', '=', 'users.id')->whereNull('community_posts.deleted_at')->whereNull('community_threads.deleted_at'), 'community_threads.title', 'users.display_name', 'community_posts')->get(['community_posts.id', DB::raw("'reply' as type"), 'community_threads.title', DB::raw("COALESCE(users.display_name, community_posts.author_name_snapshot, '削除済みユーザー') as author"), 'community_posts.created_at', 'community_posts.updated_at']);
            $rows = $rows->concat($replies);
        }
        $sort = $data['sort'] ?? 'updated_at';
        $direction = $data['direction'] ?? 'desc';
        $rows = $rows->sortBy($sort, SORT_REGULAR, $direction === 'desc')->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = new LengthAwarePaginator($rows->forPage($page, 30), $rows->count(), 30, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('admin.content', ['items' => $items]);
    }

    public function deleteContent(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['items' => ['required', 'array', 'max:100'], 'items.*' => ['required', 'regex:/^(diary|thread|reply):[0-9A-Z]{26}$/']]);
        DB::transaction(function () use ($data): void {
            foreach ($data['items'] as $item) {
                [$type, $id] = explode(':', $item, 2);
                $table = ['diary' => 'diaries', 'thread' => 'community_threads', 'reply' => 'community_posts'][$type];
                DB::table($table)->where('id', $id)->whereNull('deleted_at')->update(['deleted_at' => now(), 'updated_at' => now()]);
            }
        });
        $audit->log($request, 'content.bulk_deleted', 'content', null, ['count' => count($data['items'])]);

        return back()->with('status', '選択した投稿を論理削除しました。');
    }

    public function categories(): View
    {
        $categories = DB::table('community_categories')
            ->select('community_categories.*')
            ->selectSub(
                DB::table('community_threads')
                    ->selectRaw('count(*)')
                    ->whereColumn('community_threads.category_id', 'community_categories.id'),
                'thread_count'
            )
            ->orderBy('sort_order')
            ->get();

        return view('admin.community-categories', ['categories' => $categories]);
    }

    public function saveCategory(Request $request, AuditLogger $audit, UpdateTimestamp $timestamp, ?int $category = null): RedirectResponse
    {
        if ($category !== null) {
            DB::table('community_categories')->where('id', $category)->firstOrFail();
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('community_categories', 'slug')->ignore($category)], 'description' => ['nullable', 'string', 'max:500'], 'sort_order' => ['required', 'integer', 'between:0,65535'], 'is_active' => ['required', 'boolean']]);
        if ($category === null) {
            $category = DB::table('community_categories')->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);
        } else {
            $currentUpdatedAt = DB::table('community_categories')->where('id', $category)->value('updated_at');
            DB::table('community_categories')->where('id', $category)->update([...$data, 'updated_at' => $timestamp->value($request, $currentUpdatedAt)]);
        }
        $audit->log($request, 'community_category.saved', 'community_category', null, ['id' => $category, ...$data]);

        return back()->with('status', 'カテゴリを保存しました。');
    }

    public function deleteCategory(Request $request, int $category, AuditLogger $audit): RedirectResponse
    {
        $current = DB::table('community_categories')->where('id', $category)->firstOrFail();

        if ($current->slug === 'uncategorized') {
            throw ValidationException::withMessages([
                'category_'.$category => '「未分類」カテゴリは削除できません。',
            ]);
        }

        if (DB::table('community_threads')->where('category_id', $category)->exists()) {
            throw ValidationException::withMessages([
                'category_'.$category => '使用中のカテゴリは削除できません。',
            ]);
        }

        DB::table('community_categories')->where('id', $category)->delete();
        $audit->log($request, 'community_category.deleted', 'community_category', (array) $current, ['id' => $category]);

        return back()->with('status', 'カテゴリを削除しました。');
    }

    public function announcements(): View
    {
        return view('admin.announcements', ['announcements' => DB::table('announcements')->whereNull('deleted_at')->latest('created_at')->paginate(20)]);
    }

    public function saveAnnouncement(Request $request, AuditLogger $audit, UpdateTimestamp $timestamp, ?string $id = null): RedirectResponse
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
            $currentUpdatedAt = DB::table('announcements')->where('id', $id)->value('updated_at');
            DB::table('announcements')->where('id', $id)->update([...$data, 'updated_at' => $timestamp->value($request, $currentUpdatedAt)]);
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

    public function images(Request $request): View
    {
        $data = $request->validate(['purpose' => ['nullable', Rule::in(['header', 'banner', 'none', 'content', 'avatar'])], 'user' => ['nullable', 'string', 'max:100'], 'filename' => ['nullable', 'string', 'max:255'], 'uploaded_from' => ['nullable', 'date'], 'uploaded_to' => ['nullable', 'date']]);
        $query = DB::table('attachments')->leftJoin('users', 'attachments.uploaded_by_user_id', '=', 'users.id')->whereNull('attachments.deleted_at')->where('attachments.status', 'ready')
            ->when($data['purpose'] ?? null, function ($q, $purpose): void {
                if ($purpose === 'none') {
                    $q->whereNull('attachments.purpose');
                } elseif (in_array($purpose, ['header', 'banner'], true)) {
                    $q->where('attachments.purpose', $purpose);
                } elseif ($purpose === 'avatar') {
                    $q->where(fn ($q) => $q->whereExists(fn ($x) => $x->selectRaw('1')->from('users as au')->whereColumn('au.avatar_attachment_id', 'attachments.id'))->orWhereExists(fn ($x) => $x->selectRaw('1')->from('characters as ac')->whereColumn('ac.avatar_attachment_id', 'attachments.id')));
                } else {
                    $q->whereExists(fn ($x) => $x->selectRaw('1')->from('attachables')->whereColumn('attachables.attachment_id', 'attachments.id'));
                }
            })->when($data['user'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('users.display_name', 'like', '%'.$v.'%')->orWhere('users.login_id', 'like', '%'.$v.'%')))
            ->when($data['filename'] ?? null, fn ($q, $v) => $q->where('attachments.original_name', 'like', '%'.$v.'%'))->when($data['uploaded_from'] ?? null, fn ($q, $v) => $q->whereDate('attachments.created_at', '>=', $v))->when($data['uploaded_to'] ?? null, fn ($q, $v) => $q->whereDate('attachments.created_at', '<=', $v));

        return view('admin.images', ['images' => $query->latest('attachments.created_at')->paginate(30, ['attachments.*', 'users.display_name as uploader_name'])->withQueryString()]);
    }

    public function upload(Request $request, ImageUpload $upload, AuditLogger $audit): RedirectResponse
    {
        $request->validate(['image' => ['required', 'file'], 'purpose' => ['nullable', Rule::in(['header', 'banner'])], 'slot' => ['nullable', 'in:header'], 'alt_text' => ['nullable', 'string', 'max:255']]);
        $file = $request->file('image');
        if (! $file instanceof UploadedFile) {
            abort(422);
        }
        $purpose = $request->input('slot') === 'header' ? 'header' : $request->input('purpose');
        $image = $upload->store($file, $request->user(), 'public', $purpose);
        if ($request->input('slot') === 'header') {
            DB::table('site_assets')->updateOrInsert(['slot' => 'header'], ['id' => (string) Str::ulid(), 'attachment_id' => $image->id, 'alt_text' => $request->input('alt_text'), 'updated_by_user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $audit->log($request, 'site_image.uploaded', $image);

        return back()->with('status', '画像をアップロードしました。');
    }

    public function deleteImages(Request $request, AuditLogger $audit, AttachmentCleanup $cleanup): RedirectResponse
    {
        $ids = $request->validate(['images' => ['required', 'array', 'max:100'], 'images.*' => ['string', 'exists:attachments,id']])['images'];
        foreach ($ids as $id) {
            abort_if($cleanup->inUse($id), 422, '使用中の画像は削除できません。');
        }
        foreach ($ids as $id) {
            $cleanup->deleteIfUnused($id);
        }
        $audit->log($request, 'attachments.bulk_deleted', 'attachment', null, ['count' => count($ids)]);

        return back()->with('status', '選択した画像を削除しました。');
    }
}
