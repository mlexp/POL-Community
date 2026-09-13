<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\CommunityThread;
use App\Models\Diary;
use App\Models\User;
use Database\Seeders\SiteDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContentDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiteDefaultsSeeder::class);
    }

    public function test_home_search_and_direct_pages_enforce_publication_and_visibility(): void
    {
        $owner = User::factory()->create();
        foreach (['public', 'members', 'private'] as $visibility) {
            Diary::create(['user_id' => $owner->id, 'title' => '冒険-'.$visibility, 'body_html' => '<p>冒険</p>', 'excerpt' => '日記', 'visibility' => $visibility, 'status' => 'published', 'published_at' => now()->subMinute()]);
            DB::table('announcements')->insert(['id' => (string) Str::ulid(), 'title' => '告知-'.$visibility, 'summary' => '冒険', 'visibility' => $visibility, 'status' => 'published', 'published_at' => now()->subMinute()]);
        }
        Diary::create(['user_id' => $owner->id, 'title' => '冒険-下書き', 'body_html' => '冒険', 'visibility' => 'public', 'status' => 'draft']);
        Diary::create(['user_id' => $owner->id, 'title' => '冒険-未来', 'body_html' => '冒険', 'visibility' => 'public', 'status' => 'published', 'published_at' => now()->addDay()]);
        $this->get('/')->assertOk()->assertSee('冒険-public')->assertDontSee('冒険-members')->assertDontSee('冒険-private')->assertDontSee('冒険-下書き')->assertDontSee('冒険-未来');
        $this->get('/search?q=冒険')->assertOk()->assertSee('冒険-public')->assertSee('告知-public')->assertDontSee('告知-private')->assertDontSee('冒険-members')->assertDontSee('冒険-下書き')->assertDontSee('冒険-未来');
        $private = DB::table('announcements')->where('visibility', 'private')->value('id');
        $this->get(route('announcements.show', $private))->assertNotFound();
        $public = Diary::where('visibility', 'public')->where('status', 'published')->firstOrFail();
        $this->get(route('diaries.show', $public))->assertOk()->assertSee('Xで共有')->assertSee('Facebookで共有');
        $this->actingAs($owner)->get('/search?q=冒険')->assertSee('冒険-members')->assertDontSee('冒険-private');
        $memberDiary = Diary::where('visibility', 'members')->sole();
        $this->get(route('diaries.show', $memberDiary))->assertOk()->assertDontSee('Xで共有');
        $this->actingAs(User::factory()->create(['status' => 'suspended']))->get('/search?q=冒険')->assertDontSee('冒険-members');
    }

    public function test_search_covers_replies_but_never_hidden_or_deleted_posts_and_wildcards_are_literal(): void
    {
        $user = User::factory()->create();
        $thread = CommunityThread::create(['created_by_user_id' => $user->id, 'category_id' => DB::table('community_categories')->value('id'), 'title' => '相談', 'status' => 'open', 'visibility' => 'public', 'last_posted_at' => now()]);
        $post = CommunityPost::create(['thread_id' => $thread->id, 'user_id' => $user->id, 'body_html' => '<p>合成の冒険</p>', 'status' => 'visible']);
        $this->get('/search?q=冒険')->assertSee('相談');
        $post->update(['status' => 'hidden']);
        $this->get('/search?q=冒険')->assertDontSee('相談');
        $post->update(['status' => 'visible']);
        $post->delete();
        $this->get('/search?q=冒険')->assertDontSee('相談');
        $this->get('/search?q=%25')->assertDontSee('相談');
        $this->get('/search?q=%27%20OR%201%3D1')->assertOk()->assertDontSee('相談');
    }

    public function test_admin_can_manage_announcements_categories_and_home_limit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.community-categories'))
            ->assertOk()
            ->assertSee('<fieldset class="rounded border p-4">', false)
            ->assertSee('<legend class="px-1 font-semibold">カテゴリ追加</legend>', false)
            ->assertSeeInOrder(['カテゴリ追加', '名前', 'スラッグ', '説明', '並び順', '表示', '追加'])
            ->assertSeeInOrder(['名前', 'スラッグ', '説明', '並び順', '表示', '保存'])
            ->assertDontSee('新規選択を許可')
            ->assertDontSee('更新日時を維持する')
            ->assertSee('md:grid-cols-7', false)
            ->assertSee('md:grid-cols-8', false)
            ->assertSee('class="h-9 w-[120px] rounded border"', false)
            ->assertSee('class="justify-self-end text-right text-sm text-zinc-500"', false);
        $this->post(route('admin.community-categories.store'), ['name' => 'イベント', 'slug' => 'events', 'sort_order' => 1, 'is_active' => 1])->assertRedirect();
        $eventCategoryId = DB::table('community_categories')->where('slug', 'events')->value('id');
        $this->get(route('admin.community-categories'))
            ->assertOk()
            ->assertSee('form="community-category-delete-'.$eventCategoryId.'"', false)
            ->assertSee('class="h-9 justify-self-end rounded border border-red-700 px-3 py-1 text-red-700"', false);
        $this->delete(route('admin.community-categories.destroy', $eventCategoryId))->assertRedirect();
        $this->assertDatabaseMissing('community_categories', ['id' => $eventCategoryId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'community_category.deleted']);

        $usedCategoryId = DB::table('community_categories')->where('slug', 'general')->value('id');
        CommunityThread::create(['created_by_user_id' => $admin->id, 'category_id' => $usedCategoryId, 'title' => '利用中カテゴリのスレッド', 'status' => 'open', 'visibility' => 'public', 'last_posted_at' => now()]);
        $this->get(route('admin.community-categories'))
            ->assertOk()
            ->assertSee('使用中（1件）のため削除できません')
            ->assertDontSee('form="community-category-delete-'.$usedCategoryId.'"', false);
        $this->delete(route('admin.community-categories.destroy', $usedCategoryId))
            ->assertRedirect()
            ->assertSessionHasErrors('category_'.$usedCategoryId);
        $this->assertDatabaseHas('community_categories', ['id' => $usedCategoryId]);

        $this->get(route('admin.announcements'))
            ->assertOk()
            ->assertSee('<fieldset class="space-y-4 rounded border p-4">', false)
            ->assertSee('<legend class="px-1 font-semibold">更新情報を追加</legend>', false)
            ->assertSeeInOrder(['更新情報を追加', 'タイトル', '概要', '詳細URL', '追加']);
        $this->post(route('admin.announcements.store'), ['title' => 'お知らせ', 'summary' => '開始しました', 'visibility' => 'public', 'status' => 'published'])->assertRedirect();
        $this->get(route('admin.announcements'))
            ->assertOk()
            ->assertSee('お知らせ')
            ->assertDontSee('編集')
            ->assertSee('<main class="max-w-5xl p-6">', false)
            ->assertSee('form="announcement-delete-', false)
            ->assertSee('class="ml-auto rounded border border-red-700 px-3 py-1 text-red-700"', false);
        $id = DB::table('announcements')->value('id');
        $this->get(route('announcements.show', $id))->assertOk();
        $this->delete(route('admin.announcements.destroy', $id))->assertRedirect();
        $this->get(route('announcements.show', $id))->assertNotFound();
        $this->get(route('admin.images'))
            ->assertOk()
            ->assertSee('file:bg-zinc-800', false)
            ->assertSee('class="flex gap-3 md:col-span-5"', false)
            ->assertSeeInOrder(['検索', 'クリア']);
        $this->get(route('admin.content'))
            ->assertOk()
            ->assertSeeInOrder(['data-search-row="text"', 'data-search-row="created"', 'data-search-row="updated"', 'data-search-row="actions"'], false)
            ->assertSee('class="flex flex-wrap items-center gap-3" data-search-row="created"', false)
            ->assertSee('class="w-44 rounded border p-2" type="date"', false)
            ->assertSee('id="select-all-items"', false)
            ->assertSee('class="content-item-checkbox"', false)
            ->assertSee("querySelectorAll('.content-item-checkbox')", false)
            ->assertSeeInOrder(['投稿日（開始）', '～', '投稿日（終了）'])
            ->assertSeeInOrder(['更新日（開始）', '～', '更新日（終了）']);
        $this->post(route('admin.diary-categories.store'), ['name' => '攻略', 'slug' => 'strategy', 'description' => '', 'sort_order' => 1])->assertRedirect();
        $this->get(route('admin.diary-categories'))
            ->assertOk()
            ->assertDontSee('更新日時を維持する')
            ->assertSee('class="mb-8 grid items-end gap-3 rounded border p-4 md:grid-cols-6"', false)
            ->assertSee('class="grid items-end gap-3 md:grid-cols-7"', false)
            ->assertSee('class="h-10 w-[120px] rounded bg-zinc-900 p-2 text-white"', false)
            ->assertSee('class="h-9 w-[120px] rounded border"', false)
            ->assertSee('class="h-9 justify-self-end rounded border border-red-700 px-3 py-1 text-red-700"', false);
        $this->get(route('admin.banners'))
            ->assertOk()
            ->assertSee('class="banner-form mb-6 space-y-4 rounded border p-4"', false)
            ->assertSee('このバナーを表示する')
            ->assertSee('file:bg-zinc-800', false);
        $this->get(route('admin.feeds'))
            ->assertOk()
            ->assertSee('<fieldset class="rounded border p-4">', false)
            ->assertSee('<legend class="px-1 font-semibold">RSSを追加</legend>', false)
            ->assertSeeInOrder(['名称', 'URL', '公開範囲', '取得間隔（分）', '有効', '追加'])
            ->assertDontSee('更新日時を維持する');
        $this->assertDatabaseHas('audit_logs', ['action' => 'announcement.saved']);
    }
}
