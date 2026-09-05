<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'pendingUsers' => User::query()->where('status', 'pending')->count(),
            'feedSources' => DB::table('feed_sources')->count(),
            'banners' => DB::table('banners')->count(),
        ]);
    }

    public function settings(): View
    {
        return view('admin.settings', ['settings' => DB::table('site_settings')->pluck('value', 'key')->map(
            fn ($value) => is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value
        )]);
    }

    public function updateSettings(Request $request, AuditLogger $audit): RedirectResponse
    {
        $values = $request->validate([
            'site_title' => ['required', 'string', 'max:100'],
            'site_description' => ['nullable', 'string', 'max:500'],
            'site_footer_text' => ['nullable', 'string', 'max:1000'],
            'site_timezone' => ['required', 'timezone:all'],
            'registration_enabled' => ['required', 'boolean'],
            'registration_require_admin_approval' => ['required', 'boolean'],
            'home_announcement_limit' => ['required', 'integer', 'between:1,100'],
            'home_diary_limit' => ['required', 'integer', 'between:1,100'],
            'home_feed_item_limit' => ['required', 'integer', 'between:1,100'],
            'upload_image_max_bytes' => ['required', 'integer', 'between:1024,10485760'],
            'community_posting_enabled' => ['required', 'boolean'],
            'comments_enabled' => ['required', 'boolean'],
        ]);
        foreach (['registration_enabled', 'registration_require_admin_approval', 'community_posting_enabled', 'comments_enabled'] as $field) {
            $values[$field] = $request->boolean($field);
        }
        foreach (['home_announcement_limit', 'home_diary_limit', 'home_feed_item_limit', 'upload_image_max_bytes'] as $field) {
            $values[$field] = (int) $values[$field];
        }
        $mapping = [
            'site_title' => 'site.title', 'site_description' => 'site.description', 'site_footer_text' => 'site.footer_text',
            'site_timezone' => 'site.timezone', 'registration_enabled' => 'registration.enabled',
            'registration_require_admin_approval' => 'registration.require_admin_approval',
            'home_announcement_limit' => 'home.announcement_limit', 'home_diary_limit' => 'home.diary_limit',
            'home_feed_item_limit' => 'home.feed_item_limit', 'upload_image_max_bytes' => 'upload.image_max_bytes',
            'community_posting_enabled' => 'community.posting_enabled', 'comments_enabled' => 'comments.enabled',
        ];
        $before = DB::table('site_settings')->whereIn('key', array_values($mapping))->pluck('value', 'key')->all();
        foreach ($mapping as $field => $key) {
            DB::table('site_settings')->where('key', $key)->update([
                'value' => json_encode($values[$field] ?? '', JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'updated_by_user_id' => $request->user()->id,
                'updated_at' => now(),
            ]);
        }
        $audit->log($request, 'site_settings.updated', 'site_settings', $before, $values);

        return back()->with('status', 'サイト設定を更新しました。');
    }

    public function users(): View
    {
        return view('admin.users', ['users' => User::query()->orderByDesc('created_at')->paginate(30)]);
    }

    public function diaryCategories(): View
    {
        return view('admin.diary-categories', ['categories' => DB::table('diary_categories')->orderBy('sort_order')->get()]);
    }

    public function storeDiaryCategory(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'slug' => ['required', 'alpha_dash', 'max:100', 'unique:diary_categories,slug'], 'description' => ['nullable', 'string', 'max:500'], 'sort_order' => ['required', 'integer', 'between:0,65535']]);
        $id = DB::table('diary_categories')->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);
        $audit->log($request, 'diary_category.created', 'diary_category', null, ['id' => $id, ...$data]);

        return back()->with('status', '日記カテゴリを追加しました。');
    }

    public function updateDiaryCategory(Request $request, int $category, AuditLogger $audit): RedirectResponse
    {
        $current = (array) DB::table('diary_categories')->where('id', $category)->firstOrFail();
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('diary_categories', 'slug')->ignore($category)], 'description' => ['nullable', 'string', 'max:500'], 'sort_order' => ['required', 'integer', 'between:0,65535']]);
        DB::table('diary_categories')->where('id', $category)->update([...$data, 'updated_at' => now()]);
        $audit->log($request, 'diary_category.updated', 'diary_category', $current, ['id' => $category, ...$data]);

        return back()->with('status', '日記カテゴリを更新しました。');
    }

    public function deleteDiaryCategory(Request $request, int $category, AuditLogger $audit): RedirectResponse
    {
        $current = (array) DB::table('diary_categories')->where('id', $category)->firstOrFail();
        abort_if($current['slug'] === 'uncategorized', 422, '未分類カテゴリは削除できません。');
        DB::transaction(function () use ($category): void {
            $fallback = DB::table('diary_categories')->where('slug', 'uncategorized')->value('id');
            $diaryIds = DB::table('diary_category')->where('diary_category_id', $category)->pluck('diary_id');
            DB::table('diary_category')->where('diary_category_id', $category)->delete();
            foreach ($diaryIds as $diaryId) {
                if (! DB::table('diary_category')->where('diary_id', $diaryId)->exists()) {
                    DB::table('diary_category')->insertOrIgnore(['diary_id' => $diaryId, 'diary_category_id' => $fallback]);
                }
            }
            DB::table('diary_categories')->where('id', $category)->delete();
        });
        $audit->log($request, 'diary_category.deleted', 'diary_category', $current);

        return back()->with('status', '日記カテゴリを削除しました。');
    }

    public function updateUser(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $user);
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'active', 'suspended'])], 'role' => ['required', Rule::in(['member', 'admin'])]]);
        abort_if($request->user()->is($user) && ($data['status'] !== 'active' || $data['role'] !== 'admin'), 422, '自分自身の管理者権限または有効状態は解除できません。');
        $before = $user->only(['status', 'role']);
        $user->update($data);
        $audit->log($request, 'user.moderated', $user, $before, $data);

        return back()->with('status', 'ユーザーを更新しました。');
    }

    public function feeds(): View
    {
        return view('admin.feeds', ['feeds' => DB::table('feed_sources')->orderBy('name')->get()]);
    }

    public function storeFeed(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate($this->feedRules());
        $id = (string) Str::ulid();
        DB::table('feed_sources')->insert([...$data, 'id' => $id, 'url_hash' => hash('sha256', $data['url']), 'created_at' => now(), 'updated_at' => now()]);
        $audit->log($request, 'feed_source.created', 'feed_source', null, ['id' => $id, ...$data]);

        return back()->with('status', 'RSSソースを追加しました。');
    }

    public function updateFeed(Request $request, string $feed, AuditLogger $audit): RedirectResponse
    {
        $current = (array) DB::table('feed_sources')->where('id', $feed)->firstOrFail();
        $data = $request->validate($this->feedRules());
        DB::table('feed_sources')->where('id', $feed)->update([...$data, 'url_hash' => hash('sha256', $data['url']), 'updated_at' => now()]);
        $audit->log($request, 'feed_source.updated', 'feed_source', $current, ['id' => $feed, ...$data]);

        return back()->with('status', 'RSSソースを更新しました。');
    }

    public function deleteFeed(Request $request, string $feed, AuditLogger $audit): RedirectResponse
    {
        $current = (array) DB::table('feed_sources')->where('id', $feed)->firstOrFail();
        DB::table('feed_sources')->where('id', $feed)->delete();
        $audit->log($request, 'feed_source.deleted', 'feed_source', $current);

        return back()->with('status', 'RSSソースを削除しました。');
    }

    public function banners(): View
    {
        return view('admin.banners', ['banners' => DB::table('banners')->orderBy('placement')->orderBy('sort_order')->get(), 'images' => DB::table('attachments')->whereNull('deleted_at')->where('status', 'ready')->where('visibility', 'public')->latest('created_at')->get()]);
    }

    public function storeBanner(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate($this->bannerRules());
        $id = (string) Str::ulid();
        DB::table('banners')->insert([...$data, 'id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        $audit->log($request, 'banner.created', 'banner', null, ['id' => $id, ...$data]);

        return back()->with('status', 'バナーを追加しました。');
    }

    public function updateBanner(Request $request, string $banner, AuditLogger $audit): RedirectResponse
    {
        $current = (array) DB::table('banners')->where('id', $banner)->firstOrFail();
        $data = $request->validate($this->bannerRules());
        DB::table('banners')->where('id', $banner)->update([...$data, 'updated_at' => now()]);
        $audit->log($request, 'banner.updated', 'banner', $current, ['id' => $banner, ...$data]);

        return back()->with('status', 'バナーを更新しました。');
    }

    public function deleteBanner(Request $request, string $banner, AuditLogger $audit): RedirectResponse
    {
        $current = (array) DB::table('banners')->where('id', $banner)->firstOrFail();
        DB::table('banners')->where('id', $banner)->delete();
        $audit->log($request, 'banner.deleted', 'banner', $current);

        return back()->with('status', 'バナーを削除しました。');
    }

    /** @return array<string, array<int, string>> */
    private function feedRules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'url' => ['required', 'url:http,https', 'starts_with:https://', 'max:2048'], 'visibility' => ['required', Rule::in(['public', 'members'])], 'is_enabled' => ['required', 'boolean'], 'fetch_interval_minutes' => ['required', 'integer', 'between:5,10080']];
    }

    /** @return array<string, array<int, string|Rule>> */
    private function bannerRules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'image_attachment_id' => ['required', 'string', Rule::exists('attachments', 'id')->where('visibility', 'public')->where('status', 'ready')->whereNull('deleted_at')], 'destination_url' => ['required', 'url:https', 'starts_with:https://', 'max:2048'], 'alt_text' => ['required', 'string', 'max:255'], 'placement' => ['required', Rule::in(['home_header', 'home_sidebar', 'home_footer'])], 'sort_order' => ['required', 'integer', 'between:0,65535'], 'is_enabled' => ['required', 'boolean'], 'rel_sponsored' => ['required', 'boolean']];
    }
}
