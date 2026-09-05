<?php

namespace Tests\Feature;

use App\Models\Diary;
use App\Models\User;
use Database\Seeders\SiteDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiaryFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiteDefaultsSeeder::class);
    }

    public function test_member_can_publish_sanitized_diary_with_category_and_tags(): void
    {
        $user = User::factory()->create();
        $category = DB::table('diary_categories')->where('slug', 'uncategorized')->first();

        $this->actingAs($user)->get(route('diaries.create'))->assertOk();

        $response = $this->post(route('diaries.store'), [
            'title' => '冒険日記',
            'body_html' => '<h2>見出し</h2><p>Hello <strong>world</strong><script>alert(1)</script><a href="javascript:alert(2)" onclick="alert(3)">link</a></p>',
            'visibility' => 'public',
            'status' => 'published',
            'comments_enabled' => '1',
            'category_ids' => [$category->id],
            'tags' => 'FFXI, 冒険',
        ]);

        $diary = Diary::query()->sole();
        $response->assertSessionHasNoErrors()->assertRedirect(route('diaries.show', $diary));
        $this->assertStringContainsString('<strong>world</strong>', $diary->body_html);
        $this->assertStringNotContainsString('script', $diary->body_html);
        $this->assertStringNotContainsString('javascript:', $diary->body_html);
        $this->assertStringNotContainsString('onclick', $diary->body_html);
        $this->assertDatabaseCount('diary_tag', 2);
        $this->assertDatabaseHas('diary_category', ['diary_id' => $diary->id, 'diary_category_id' => $category->id]);
    }

    public function test_draft_and_private_diary_visibility_is_enforced(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $draft = $this->diary($owner, ['status' => 'draft']);
        $private = $this->diary($owner, ['visibility' => 'private']);

        $this->get(route('diaries.show', $draft))->assertNotFound();
        $this->actingAs($other)->get(route('diaries.show', $private))->assertForbidden();
        $this->actingAs($owner)->get(route('diaries.show', $draft))->assertOk();
    }

    public function test_member_cannot_edit_another_members_diary(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $diary = $this->diary($owner);

        $this->actingAs($other)->get(route('diaries.edit', $diary))->assertForbidden();
    }

    public function test_logged_in_member_can_comment_and_comment_is_sanitized(): void
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create();
        $diary = $this->diary($author);

        $this->actingAs($commenter)->post(route('diary-comments.store', $diary), [
            'body_html' => '<p>Nice!</p><img src=x onerror=alert(1)>',
        ])->assertSessionHasNoErrors()->assertRedirect(route('diaries.show', $diary));

        $comment = DB::table('diary_comments')->sole();
        $this->assertSame($commenter->id, $comment->user_id);
        $this->assertSame($commenter->display_name, $comment->author_name_snapshot);
        $this->assertStringNotContainsString('img', $comment->body_html);
        $this->assertStringNotContainsString('onerror', $comment->body_html);
    }

    public function test_guest_cannot_post_comment(): void
    {
        $diary = $this->diary(User::factory()->create());

        $this->post(route('diary-comments.store', $diary), ['body_html' => '<p>guest</p>'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('diary_comments', 0);
    }

    /** @param array<string, mixed> $overrides */
    private function diary(User $user, array $overrides = []): Diary
    {
        return Diary::create([...[
            'user_id' => $user->id,
            'title' => 'Diary',
            'body_json' => ['type' => 'doc'],
            'body_html' => '<p>Body</p>',
            'excerpt' => 'Body',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'comments_enabled' => true,
        ], ...$overrides]);
    }
}
