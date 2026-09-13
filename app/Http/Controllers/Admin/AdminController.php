<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ImageUpload;
use App\Support\AuditLogger;
use App\Support\MediaUrl;
use App\Support\UpdateTimestamp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        ), 'headerImages' => DB::table('attachments')->whereNull('deleted_at')->where('status', 'ready')->where('visibility', 'public')->where('purpose', 'header')->latest()->get(), 'currentHeader' => DB::table('site_assets')->where('slot', 'header')->value('attachment_id'), 'currentSiteLogo' => DB::table('site_assets')->where('slot', 'site_logo')->value('attachment_id'), 'timezones' => timezone_identifiers_list()]);
    }

    public function updateSettings(Request $request, AuditLogger $audit, ImageUpload $upload): RedirectResponse
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
            'home_character_diary_limit' => ['sometimes', 'integer', 'between:1,100'],
            'home_community_limit' => ['sometimes', 'integer', 'between:1,100'],
            'home_header_enabled' => ['required', 'boolean'],
            'home_heading_announcements' => ['sometimes', 'string', 'max:100'],
            'home_heading_diaries' => ['sometimes', 'string', 'max:100'],
            'home_heading_community' => ['sometimes', 'string', 'max:100'],
            'home_heading_feeds' => ['sometimes', 'string', 'max:100'],
            'header_attachment_id' => ['nullable', 'string', Rule::exists('attachments', 'id')->where('visibility', 'public')->where('status', 'ready')->where('purpose', 'header')->whereNull('deleted_at')],
            'header_image' => ['nullable', 'file'],
            'site_logo_attachment_id' => ['nullable', 'string', Rule::exists('attachments', 'id')->where('visibility', 'public')->where('status', 'ready')->where('purpose', 'header')->whereNull('deleted_at')],
            'site_logo_image' => ['nullable', 'file'],
            'home_section_order' => ['required', 'array:announcements,diaries,community,feeds', 'size:4'],
            'home_section_order.*' => ['required', 'integer', 'between:1,4', 'distinct'],
            'home_section_enabled' => ['nullable', 'array'],
            'home_section_enabled.*' => ['string', Rule::in(['announcements', 'diaries', 'community', 'feeds'])],
            'upload_image_max_bytes' => ['required', 'integer', 'between:1024,10485760'],
            'community_posting_enabled' => ['required', 'boolean'],
            'comments_enabled' => ['required', 'boolean'],
        ]);
        foreach (['registration_enabled', 'registration_require_admin_approval', 'home_header_enabled', 'community_posting_enabled', 'comments_enabled'] as $field) {
            $values[$field] = $request->boolean($field);
        }
        $values += ['home_character_diary_limit' => 5, 'home_community_limit' => 10, 'home_heading_announcements' => 'サイト更新情報', 'home_heading_diaries' => 'メンバー日記', 'home_heading_community' => 'コミュニティの新着', 'home_heading_feeds' => '外部RSS'];
        foreach (['home_announcement_limit', 'home_diary_limit', 'home_feed_item_limit', 'home_character_diary_limit', 'home_community_limit', 'upload_image_max_bytes'] as $field) {
            $values[$field] = (int) $values[$field];
        }
        $enabledSections = $request->array('home_section_enabled');
        $sectionOrder = $request->array('home_section_order');
        asort($sectionOrder);
        $values['home_sections'] = array_values(array_filter(
            array_keys($sectionOrder),
            fn (mixed $section): bool => is_string($section) && in_array($section, $enabledSections, true),
        ));
        $mapping = [
            'site_title' => 'site.title', 'site_description' => 'site.description', 'site_footer_text' => 'site.footer_text',
            'site_timezone' => 'site.timezone', 'registration_enabled' => 'registration.enabled',
            'registration_require_admin_approval' => 'registration.require_admin_approval',
            'home_announcement_limit' => 'home.announcement_limit', 'home_diary_limit' => 'home.diary_limit',
            'home_feed_item_limit' => 'home.feed_item_limit', 'upload_image_max_bytes' => 'upload.image_max_bytes',
            'home_character_diary_limit' => 'home.character_diary_limit',
            'home_community_limit' => 'home.community_limit',
            'home_header_enabled' => 'home.header_enabled',
            'home_heading_announcements' => 'home.heading.announcements', 'home_heading_diaries' => 'home.heading.diaries',
            'home_heading_community' => 'home.heading.community', 'home_heading_feeds' => 'home.heading.feeds',
            'home_sections' => 'home.sections',
            'community_posting_enabled' => 'community.posting_enabled', 'comments_enabled' => 'comments.enabled',
        ];
        $header = $request->hasFile('header_image') ? $upload->store($request->file('header_image'), $request->user(), 'public', 'header')->id : ($values['header_attachment_id'] ?? null);
        $siteLogo = $request->hasFile('site_logo_image') ? $upload->store($request->file('site_logo_image'), $request->user(), 'public', 'header')->id : ($values['site_logo_attachment_id'] ?? null);
        unset($values['home_section_order'], $values['home_section_enabled'], $values['header_attachment_id'], $values['header_image'], $values['site_logo_attachment_id'], $values['site_logo_image']);
        $before = DB::table('site_settings')->whereIn('key', array_values($mapping))->pluck('value', 'key')->all();
        foreach ($mapping as $field => $key) {
            DB::table('site_settings')->updateOrInsert(['key' => $key], [
                'value' => json_encode($values[$field] ?? '', JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'updated_by_user_id' => $request->user()->id,
                'updated_at' => now(),
            ]);
        }
        if ($header !== null) {
            DB::table('site_assets')->updateOrInsert(['slot' => 'header'], ['id' => (string) Str::ulid(), 'attachment_id' => $header, 'updated_by_user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($siteLogo !== null) {
            DB::table('site_assets')->updateOrInsert(['slot' => 'site_logo'], ['id' => (string) Str::ulid(), 'attachment_id' => $siteLogo, 'updated_by_user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $audit->log($request, 'site_settings.updated', 'site_settings', $before, $values);

        return back()->with('status', 'サイト設定を更新しました。');
    }

    public function users(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = User::query()->with('characters')->when($data['q'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('display_name', 'like', '%'.$v.'%')->orWhere('login_id', 'like', '%'.$v.'%')->orWhere('email', 'like', '%'.$v.'%')));

        return view('admin.users', ['users' => $query->orderByDesc('updated_at')->paginate(30)->withQueryString()]);
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

    public function updateDiaryCategory(Request $request, int $category, AuditLogger $audit, UpdateTimestamp $timestamp): RedirectResponse
    {
        $current = (array) DB::table('diary_categories')->where('id', $category)->firstOrFail();
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('diary_categories', 'slug')->ignore($category)], 'description' => ['nullable', 'string', 'max:500'], 'sort_order' => ['required', 'integer', 'between:0,65535']]);
        DB::table('diary_categories')->where('id', $category)->update([...$data, 'updated_at' => $timestamp->value($request, $current['updated_at'])]);
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

    public function updateUser(Request $request, User $user, AuditLogger $audit, UpdateTimestamp $timestamp): RedirectResponse
    {
        Gate::authorize('update', $user);
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'active', 'suspended'])], 'role' => ['required', Rule::in(['member', 'admin'])]]);
        abort_if($request->user()->is($user) && ($data['status'] !== 'active' || $data['role'] !== 'admin'), 422, '自分自身の管理者権限または有効状態は解除できません。');
        $before = $user->only(['status', 'role']);
        $timestamp->update($request, $user, $data);
        $audit->log($request, 'user.moderated', $user, $before, $data);

        return back()->with('status', 'ユーザーを更新しました。');
    }

    public function deleteUser(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, '自分自身は削除できません。');
        DB::transaction(function () use ($user): void {
            $user->characters()->delete();
            DB::table('diaries')->where('user_id', $user->id)->whereNull('deleted_at')->update(['deleted_at' => now(), 'updated_at' => now()]);
            $user->delete();
        });
        $audit->log($request, 'user.deleted', $user);

        return back()->with('status', 'ユーザーを論理削除しました。');
    }

    public function feeds(): View
    {
        return view('admin.feeds', ['feeds' => DB::table('feed_sources')->orderBy('name')->get()]);
    }

    public function storeFeed(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate($this->feedRules());
        $this->ensureHttpsFeedUrl($data['url']);
        $id = (string) Str::ulid();
        DB::table('feed_sources')->insert([...$data, 'id' => $id, 'url_hash' => hash('sha256', $data['url']), 'created_at' => now(), 'updated_at' => now()]);
        $audit->log($request, 'feed_source.created', 'feed_source', null, ['id' => $id, ...$data]);

        return back()->with('status', 'RSSソースを追加しました。');
    }

    public function updateFeed(Request $request, string $feed, AuditLogger $audit, UpdateTimestamp $timestamp): RedirectResponse
    {
        $current = (array) DB::table('feed_sources')->where('id', $feed)->firstOrFail();
        $data = $request->validate($this->feedRules());
        $this->ensureHttpsFeedUrl($data['url']);
        DB::table('feed_sources')->where('id', $feed)->update([...$data, 'url_hash' => hash('sha256', $data['url']), 'updated_at' => $timestamp->value($request, $current['updated_at'])]);
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
        return view('admin.banners', ['banners' => DB::table('banners')->orderBy('placement')->orderBy('sort_order')->get(), 'images' => DB::table('attachments')->whereNull('deleted_at')->where('status', 'ready')->where('visibility', 'public')->where('purpose', 'banner')->latest('created_at')->get()]);
    }

    public function storeBanner(Request $request, AuditLogger $audit, ImageUpload $upload): RedirectResponse
    {
        $data = $request->validate($this->bannerRules(false));
        $this->ensureBannerImagePurpose($data['image_attachment_id'] ?? null);
        if ($request->hasFile('image')) {
            $data['image_attachment_id'] = $upload->store($request->file('image'), $request->user(), 'public', 'banner')->id;
        }
        unset($data['image']);
        $id = (string) Str::ulid();
        DB::table('banners')->insert([...$data, 'id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        $audit->log($request, 'banner.created', 'banner', null, ['id' => $id, ...$data]);

        return back()->with('status', 'バナーを追加しました。');
    }

    public function updateBanner(Request $request, string $banner, AuditLogger $audit, UpdateTimestamp $timestamp, ImageUpload $upload): RedirectResponse
    {
        $current = (array) DB::table('banners')->where('id', $banner)->firstOrFail();
        $data = $request->validate($this->bannerRules());
        $this->ensureBannerImagePurpose($data['image_attachment_id'] ?? null);
        if ($request->hasFile('image')) {
            $data['image_attachment_id'] = $upload->store($request->file('image'), $request->user(), 'public', 'banner')->id;
        }
        unset($data['image']);
        DB::table('banners')->where('id', $banner)->update([...$data, 'updated_at' => $timestamp->value($request, $current['updated_at'])]);
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
        return ['name' => ['required', 'string', 'max:100'], 'url' => ['required', 'url:http,https', 'max:2048'], 'visibility' => ['required', Rule::in(['public', 'members'])], 'is_enabled' => ['required', 'boolean'], 'fetch_interval_minutes' => ['required', 'integer', 'between:5,10080']];
    }

    private function ensureHttpsFeedUrl(string $url): void
    {
        if (! app(MediaUrl::class)->https($url)) {
            throw ValidationException::withMessages(['url' => 'RSS URLはHTTPSで指定してください。']);
        }
    }

    /** @return array<string, array<int, string|Rule>> */
    private function bannerRules(bool $existing = true): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'image_attachment_id' => [$existing ? 'required_without:image' : 'required_without:image', 'nullable', 'string', Rule::exists('attachments', 'id')->where('visibility', 'public')->where('status', 'ready')->whereNull('deleted_at')], 'image' => ['nullable', 'file'], 'destination_url' => ['required', 'url:https', 'max:2048'], 'alt_text' => ['required', 'string', 'max:255'], 'placement' => ['required', Rule::in(['home_header', 'home_sidebar', 'home_footer'])], 'sort_order' => ['required', 'integer', 'between:0,65535'], 'is_enabled' => ['required', 'boolean'], 'rel_sponsored' => ['required', 'boolean']];
    }

    private function ensureBannerImagePurpose(?string $id): void
    {
        if ($id !== null && ! DB::table('attachments')->where('id', $id)->where('purpose', 'banner')->exists()) {
            throw ValidationException::withMessages(['image_attachment_id' => 'バナー用として登録された画像を選択してください。']);
        }
    }
}
