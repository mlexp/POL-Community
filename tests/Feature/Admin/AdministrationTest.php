<?php

namespace Tests\Feature\Admin;

use App\Models\Character;
use App\Models\Diary;
use App\Models\User;
use Database\Seeders\FfxiMasterSeeder;
use Database\Seeders\SiteDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_access_administration(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_can_approve_user_and_action_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['status' => 'pending']);

        $this->actingAs($admin)->patch(route('admin.users.update', $member), [
            'status' => 'active',
            'role' => 'member',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $member->id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $admin->id, 'action' => 'user.moderated', 'subject_id' => $member->id]);
    }

    public function test_admin_can_update_registration_settings(): void
    {
        $this->seed(SiteDefaultsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.settings.update'), $this->settingsPayload([
            'registration_enabled' => '0',
            'home_section_order' => ['announcements' => 4, 'diaries' => 2, 'community' => 3, 'feeds' => 1],
            'home_section_enabled' => ['feeds', 'diaries'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('false', DB::table('site_settings')->where('key', 'registration.enabled')->value('value'));
        $this->assertSame('["feeds","diaries"]', DB::table('site_settings')->where('key', 'home.sections')->value('value'));
        $this->get(route('home'))->assertSeeInOrder(['外部RSS', 'メンバー日記'])->assertDontSee('サイト更新情報')->assertDontSee('コミュニティの新着');
        $this->assertDatabaseHas('audit_logs', ['action' => 'site_settings.updated']);
    }

    public function test_admin_can_manage_https_feed_sources(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.feeds.store'), [
            'name' => 'Official news',
            'url' => 'https://example.com/feed.xml',
            'visibility' => 'public',
            'is_enabled' => '0',
            'fetch_interval_minutes' => 60,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('feed_sources', ['name' => 'Official news', 'is_enabled' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'feed_source.created']);
        $feed = DB::table('feed_sources')->first();

        $this->get(route('admin.feeds'))
            ->assertOk()
            ->assertSee('class="mb-3 grid items-end gap-2 border-t py-3 md:grid-cols-8"', false)
            ->assertSee('class="flex items-end gap-3 md:col-span-2"', false)
            ->assertSee('form="feed-delete-'.$feed->id.'"', false)
            ->assertSee('class="h-10 justify-self-end rounded border border-red-700 px-3 py-1 text-red-700"', false)
            ->assertSeeInOrder(['名称', 'URL', '公開範囲', '取得間隔（分）', '有効', '保存'])
            ->assertDontSee('更新日時を維持する');

        $originalUpdatedAt = $feed->updated_at;
        $this->travel(1)->day();
        $this->put(route('admin.feeds.update', $feed->id), [
            'name' => 'Updated feed', 'url' => 'https://example.com/updated.xml', 'visibility' => 'public', 'is_enabled' => '0', 'fetch_interval_minutes' => 60,
            'preserve_updated_at' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertEquals($originalUpdatedAt, DB::table('feed_sources')->where('id', $feed->id)->value('updated_at'));
        $this->put(route('admin.feeds.update', $feed->id), [
            'name' => 'Updated feed', 'url' => 'http://example.com/feed.xml', 'visibility' => 'public', 'is_enabled' => '0', 'fetch_interval_minutes' => 60,
        ])->assertSessionHasErrors(['url' => 'RSS URLはHTTPSで指定してください。']);
    }

    public function test_deleting_user_hides_their_characters_and_diaries(): void
    {
        $this->seed(FfxiMasterSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();
        $character = Character::create(['user_id' => $member->id, 'game_id' => DB::table('games')->where('code', 'ffxi')->value('id'), 'name' => 'Deleted Hero', 'visibility' => 'public']);
        $diary = Diary::create(['user_id' => $member->id, 'character_id' => $character->id, 'title' => 'Deleted Diary', 'body_html' => '<p>body</p>', 'visibility' => 'public', 'status' => 'published', 'published_at' => now(), 'comments_enabled' => true]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $member))->assertRedirect();

        $this->assertSoftDeleted($member);
        $this->assertSoftDeleted($character);
        $this->assertSoftDeleted($diary);
        $this->get(route('characters.index'))->assertDontSee('Deleted Hero');
        $this->get(route('diaries.index'))->assertDontSee('Deleted Diary');
    }

    public function test_admin_can_render_site_settings_with_configurable_home_sections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('トップページの表示内容')
            ->assertSeeInOrder(['トップページのヘッダー', 'ヘッダーを表示する', '登録済み画像'])
            ->assertSee('タイムゾーン')
            ->assertSeeInOrder(['サイト名', 'サイト名位置の画像', '用途: ヘッダー'])
            ->assertSee('file:bg-zinc-800', false);
    }

    public function test_admin_can_set_a_site_logo_for_the_sticky_public_header(): void
    {
        $this->seed(SiteDefaultsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.settings.update'), $this->settingsPayload([
            'site_title' => 'Logo Alt Text',
            'site_logo_image' => UploadedFile::fake()->image('site-logo.png', 240, 80),
            'header_image' => UploadedFile::fake()->image('home-header.png', 800, 300),
        ]))->assertSessionHasNoErrors();

        $logoId = DB::table('site_assets')->where('slot', 'site_logo')->value('attachment_id');
        $this->assertNotNull($logoId);
        $this->assertDatabaseHas('attachments', ['id' => $logoId, 'purpose' => 'header']);
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('class="sticky top-0 z-50', false)
            ->assertSee('class="h-auto max-h-16 max-w-full"', false)
            ->assertSee('class="mb-6 h-auto max-w-full rounded"', false)
            ->assertSee('alt="Logo Alt Text"', false);

        DB::table('site_assets')->where('slot', 'header')->delete();
        $this->get(route('home'))->assertSee('<h1 class="my-5 text-3xl font-bold">Logo Alt Text</h1>', false);

        $this->put(route('admin.settings.update'), $this->settingsPayload([
            'site_title' => 'Logo Alt Text',
            'home_header_enabled' => '0',
        ]))->assertSessionHasNoErrors();
        $this->get(route('home'))
            ->assertDontSee('class="mb-6 h-auto max-w-full rounded"', false)
            ->assertDontSee('<h1 class="my-5 text-3xl font-bold">', false);
        $this->actingAs($admin)->get(route('dashboard'))->assertDontSee('sticky top-0 z-50', false);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function settingsPayload(array $overrides = []): array
    {
        return [...[
            'site_title' => 'POL Community', 'site_description' => '', 'site_footer_text' => '', 'site_timezone' => 'Asia/Tokyo',
            'registration_enabled' => '1', 'registration_require_admin_approval' => '1',
            'home_announcement_limit' => 10, 'home_diary_limit' => 10, 'home_feed_item_limit' => 10,
            'home_character_diary_limit' => 5, 'home_community_limit' => 10,
            'home_header_enabled' => '1',
            'home_section_order' => ['announcements' => 1, 'diaries' => 2, 'community' => 3, 'feeds' => 4],
            'home_section_enabled' => ['announcements', 'diaries', 'community', 'feeds'],
            'upload_image_max_bytes' => 1048576, 'community_posting_enabled' => '1', 'comments_enabled' => '1',
        ], ...$overrides];
    }
}
