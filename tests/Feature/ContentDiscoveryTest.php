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
        $this->actingAs($admin)->get(route('admin.community-categories'))->assertOk();
        $this->post(route('admin.community-categories.store'), ['name' => 'イベント', 'slug' => 'events', 'sort_order' => 1, 'is_active' => 1])->assertRedirect();
        $this->get(route('admin.announcements'))->assertOk();
        $this->post(route('admin.announcements.store'), ['title' => 'お知らせ', 'summary' => '開始しました', 'visibility' => 'public', 'status' => 'published'])->assertRedirect();
        $this->get(route('admin.announcements'))->assertOk()->assertSee('お知らせ');
        $id = DB::table('announcements')->value('id');
        $this->get(route('announcements.show', $id))->assertOk();
        $this->delete(route('admin.announcements.destroy', $id))->assertRedirect();
        $this->get(route('announcements.show', $id))->assertNotFound();
        $this->get(route('admin.images'))->assertOk();
        $this->get(route('admin.banners'))->assertOk();
        $this->get(route('admin.feeds'))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'announcement.saved']);
    }
}
