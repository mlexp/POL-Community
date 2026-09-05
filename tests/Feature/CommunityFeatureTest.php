<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\CommunityThread;
use App\Models\User;
use Database\Seeders\SiteDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommunityFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiteDefaultsSeeder::class);
    }

    public function test_member_can_create_reply_edit_and_delete_with_float_and_counts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('community.create'))->assertOk();
        $this->post(route('community.store'), $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $thread = CommunityThread::sole();
        $post = CommunityPost::sole();
        $this->assertSame(1, $thread->post_count);
        $this->assertStringNotContainsString('<script', $post->body_html);
        $this->get(route('community.show', $thread))->assertOk()->assertSee('冒険の相談');
        $this->travel(1)->minutes();
        $this->post(route('community.reply', $thread), ['body_html' => '<p>返信</p>'])->assertRedirect();
        $this->assertSame(2, $thread->fresh()->post_count);
        $this->assertTrue($thread->fresh()->last_posted_at > $thread->last_posted_at);
        $reply = CommunityPost::where('id', '!=', $post->id)->sole();
        $this->get(route('community-posts.edit', $reply))->assertOk();
        $this->put(route('community-posts.update', $reply), ['body_html' => '<p>更新した返信</p>'])->assertRedirect();
        $this->get(route('community.show', $thread))->assertSee('更新した返信');
        $this->delete(route('community-posts.destroy', $reply))->assertRedirect();
        $this->assertSame(1, $thread->fresh()->post_count);
        $this->assertEquals($thread->last_posted_at, $thread->fresh()->last_posted_at);
        $this->get(route('community.edit', $thread))->assertOk();
        $this->delete(route('community.destroy', $thread))->assertRedirect(route('community.index'));
        $this->get(route('community.show', $thread))->assertNotFound();
    }

    public function test_guests_inactive_users_and_other_members_cannot_mutate(): void
    {
        $this->post(route('community.store'), $this->data())->assertRedirect(route('login'));
        foreach (['pending', 'suspended'] as $status) {
            $this->actingAs(User::factory()->create(['status' => $status]))->post(route('community.store'), $this->data())->assertForbidden();
        }
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('community.store'), $this->data())->assertRedirect();
        $thread = CommunityThread::sole();
        $post = CommunityPost::sole();
        $this->actingAs(User::factory()->create())->put(route('community.update', $thread), $this->data())->assertForbidden();
        $this->delete(route('community-posts.destroy', $post))->assertForbidden();
        $this->get(route('media.edit', ['community_post', $post->id]))->assertForbidden();
    }

    public function test_visibility_closed_threads_posting_switch_and_rate_limits(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('community.store'), $this->data())->assertRedirect();
        $thread = CommunityThread::sole();
        $thread->update(['visibility' => 'private']);
        $this->actingAs(User::factory()->create())->get(route('community.show', $thread))->assertForbidden();
        $this->get(route('community.index'))->assertDontSee('冒険の相談');
        $thread->update(['visibility' => 'public', 'status' => 'closed']);
        $this->post(route('community.reply', $thread), ['body_html' => '返信'])->assertForbidden();
        $thread->update(['status' => 'open']);
        DB::table('site_settings')->where('key', 'community.posting_enabled')->update(['value' => 'false']);
        $this->post(route('community.reply', $thread), ['body_html' => '返信'])->assertForbidden();
        DB::table('site_settings')->where('key', 'community.posting_enabled')->update(['value' => 'true']);
        for ($i = 0; $i < 8; $i++) {
            $this->post(route('community.reply', $thread), ['body_html' => '返信'])->assertRedirect();
        }
        $this->post(route('community.reply', $thread), ['body_html' => '返信'])->assertStatus(429);
    }

    public function test_listing_floats_threads_by_last_reply_and_excludes_hidden(): void
    {
        $this->actingAs(User::factory()->create())->post(route('community.store'), $this->data());
        $first = CommunityThread::sole();
        $this->travel(1)->minutes();
        $this->post(route('community.store'), [...$this->data(), 'title' => '新しいスレッド']);
        $this->get(route('community.index'))->assertSeeInOrder(['新しいスレッド', '冒険の相談']);
        $this->travel(1)->minutes();
        $this->post(route('community.reply', $first), ['body_html' => '追加']);
        $this->get(route('community.index'))->assertSeeInOrder(['冒険の相談', '新しいスレッド']);
        $first->update(['status' => 'hidden']);
        $this->get(route('community.index'))->assertDontSee('冒険の相談');
    }

    /** @return array<string,mixed> */
    private function data(): array
    {
        return ['title' => '冒険の相談', 'category_id' => DB::table('community_categories')->value('id'), 'visibility' => 'public', 'body_html' => '<p>本文</p><script>alert(1)</script>'];
    }
}
