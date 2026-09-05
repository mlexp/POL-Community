<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\SiteDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ]))->assertSessionHasNoErrors();

        $this->assertSame('false', DB::table('site_settings')->where('key', 'registration.enabled')->value('value'));
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
    }

    /** @param array<string, string|int> $overrides
     * @return array<string, string|int>
     */
    private function settingsPayload(array $overrides = []): array
    {
        return [...[
            'site_title' => 'POL Community', 'site_description' => '', 'site_footer_text' => '', 'site_timezone' => 'Asia/Tokyo',
            'registration_enabled' => '1', 'registration_require_admin_approval' => '1',
            'home_announcement_limit' => 10, 'home_diary_limit' => 10, 'home_feed_item_limit' => 10,
            'upload_image_max_bytes' => 1048576, 'community_posting_enabled' => '1', 'comments_enabled' => '1',
        ], ...$overrides];
    }
}
