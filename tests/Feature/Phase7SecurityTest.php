<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Diary;
use App\Models\User;
use Database\Seeders\FfxiMasterSeeder;
use Database\Seeders\SiteDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase7SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SiteDefaultsSeeder::class, FfxiMasterSeeder::class]);
    }

    public function test_state_changing_routes_reject_requests_without_a_csrf_token_outside_testing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->app->instance('env', 'production');

        try {
            $this->withoutExceptionHandling()->post('/diaries', [
                'title' => 'CSRF',
                'body_html' => '<p>本文</p>',
                'visibility' => 'public',
                'status' => 'published',
                'comments_enabled' => '1',
            ]);
            $this->fail('CSRFトークンなしの更新リクエストが受理されました。');
        } catch (TokenMismatchException) {
            $this->addToAssertionCount(1);
        } finally {
            $this->app->instance('env', 'testing');
        }

        $this->assertDatabaseCount('diaries', 0);
    }

    public function test_suspended_and_password_reset_required_users_cannot_open_members_only_character_urls(): void
    {
        $owner = User::factory()->create();
        $character = Character::create([
            'user_id' => $owner->id,
            'game_id' => DB::table('games')->where('code', 'ffxi')->value('id'),
            'name' => 'Members Only',
            'visibility' => 'members',
        ]);

        $this->get(route('characters.show', $character))->assertForbidden();
        $this->actingAs(User::factory()->create(['status' => 'suspended']))
            ->get(route('characters.show', $character))->assertForbidden();
        $this->actingAs(User::factory()->create(['password_reset_required' => true]))
            ->get(route('characters.show', $character))->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->get(route('characters.show', $character))->assertOk();
    }

    public function test_private_diary_and_its_image_are_denied_by_direct_url_to_other_users(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $diary = Diary::create([
            'user_id' => $owner->id,
            'title' => '非公開',
            'body_html' => '<p>秘密</p>',
            'visibility' => 'private',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'comments_enabled' => true,
        ]);
        $attachmentId = (string) Str::ulid();
        DB::table('attachments')->insert([
            'id' => $attachmentId,
            'uploaded_by_user_id' => $owner->id,
            'disk' => 'local',
            'path' => 'private/missing.webp',
            'path_hash' => hash('sha256', 'private/missing.webp'),
            'original_name' => 'secret.webp',
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'size_bytes' => 1,
            'width' => 1,
            'height' => 1,
            'sha256' => hash('sha256', 'x'),
            'visibility' => 'private',
            'status' => 'ready',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('attachables')->insert([
            'attachment_id' => $attachmentId,
            'attachable_type' => 'diary',
            'attachable_id' => $diary->id,
            'sort_order' => 0,
        ]);

        $this->get(route('diaries.show', $diary))->assertForbidden();
        $this->get(route('images.show', $attachmentId))->assertForbidden();
        $this->actingAs($other)->get(route('diaries.show', $diary))->assertForbidden();
        $this->get(route('images.show', $attachmentId))->assertForbidden();
        $this->actingAs($owner)->get(route('diaries.show', $diary))->assertOk();
    }

    public function test_search_treats_sql_metacharacters_as_literal_input(): void
    {
        $owner = User::factory()->create();
        Diary::create([
            'user_id' => $owner->id,
            'title' => '通常の日記',
            'body_html' => '<p>本文</p>',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'comments_enabled' => true,
        ]);

        foreach (["' OR 1=1 --", '%', '_', '\\\\'] as $payload) {
            $this->get(route('search', ['q' => $payload]))
                ->assertOk()
                ->assertDontSee('通常の日記');
        }
    }

    public function test_registration_is_limited_to_five_attempts_per_ip_per_minute(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/register')->post('/register', [])->assertSessionHasErrors();
        }

        $this->from('/register')->post('/register', [])->assertTooManyRequests();
    }

    public function test_login_is_limited_to_five_failed_attempts_per_identity_and_ip(): void
    {
        $user = User::factory()->create();
        $payload = ['login' => $user->email, 'password' => 'incorrect'];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), $payload)->assertSessionHasErrors('login');
        }

        $this->post(route('login.store'), $payload)->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_comment_spam_is_limited_to_ten_posts_per_minute(): void
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create();
        $diary = Diary::create([
            'user_id' => $author->id,
            'title' => 'コメント対象',
            'body_html' => '<p>本文</p>',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'comments_enabled' => true,
        ]);
        $this->actingAs($commenter);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post(route('diary-comments.store', $diary), ['body_html' => '<p>コメント</p>'])
                ->assertRedirect();
        }

        $this->post(route('diary-comments.store', $diary), ['body_html' => '<p>超過</p>'])
            ->assertTooManyRequests();
        $this->assertDatabaseCount('diary_comments', 10);
    }
}
