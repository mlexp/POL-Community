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

        $this->actingAs($user)->get(route('diaries.create'))
            ->assertOk()
            ->assertSeeInOrder(['見出し', '右寄せ', '中央寄せ', '左寄せ', '文字を大きく', '文字を小さく', '太字', '斜体', 'アンダーライン', '取り消し線', '上付き', '下付き', '文字色', '背景色', '箇条書き', '番号', '書式解除', 'リンク', 'リンク解除'])
            ->assertSee('role="separator" aria-orientation="vertical"', false)
            ->assertSee('data-rich-text-control data-command="formatBlock"', false)
            ->assertSee('src="'.asset('assets/editor/title_24dp_1F1F1F.svg').'" alt="見出し"', false)
            ->assertSee('src="'.asset('assets/editor/format_color_text_24dp_1F1F1F.svg').'" alt="文字色"', false)
            ->assertSee('src="'.asset('assets/editor/link_off_24dp_1F1F1F.svg').'" alt="リンク解除"', false)
            ->assertSee('aria-label="文字色 #FF0000"', false)
            ->assertSee('aria-label="背景色 #FFD1D1"', false)
            ->assertSee('<details data-rich-text-palette class="relative">', false)
            ->assertSee('class="absolute z-10 mt-1 grid w-36 grid-cols-4 gap-1', false)
            ->assertSee('class="h-7 w-7 shrink-0 rounded border border-zinc-400"', false);

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

    public function test_public_diary_index_keeps_the_public_header_and_my_diaries_uses_the_sidebar(): void
    {
        $member = User::factory()->create();
        $ownDiary = $this->diary($member, ['title' => '自分の投稿', 'body_html' => '<p>自分の本文</p>', 'excerpt' => '自分の本文']);
        $otherDiary = $this->diary(User::factory()->create(), ['title' => 'ほかの投稿', 'body_html' => '<p>ほかの本文</p>', 'excerpt' => 'ほかの本文']);

        $this->actingAs($member)->get(route('diaries.index'))
            ->assertOk()
            ->assertSee('placeholder="日記・コミュニティ・更新情報を検索"', false)
            ->assertDontSee('マイキャラクター')
            ->assertSee($ownDiary->title)
            ->assertSee($otherDiary->title)
            ->assertDontSee('自分の本文')
            ->assertDontSee('ほかの本文');

        $this->get(route('diaries.mine'))
            ->assertOk()
            ->assertSeeInOrder(['マイキャラクター', '自分の日記', 'トップ', 'メンバー日記', 'コミュニティ'])
            ->assertSee($ownDiary->title)
            ->assertDontSee('自分の本文')
            ->assertDontSee($otherDiary->title);
    }

    public function test_draft_and_private_diary_visibility_is_enforced(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $draft = $this->diary($owner, ['status' => 'draft']);
        $private = $this->diary($owner, ['visibility' => 'private']);

        $this->get(route('diaries.show', $draft))->assertNotFound();
        $this->actingAs($other)->get(route('diaries.show', $private))->assertForbidden();
        $this->actingAs($owner)->get(route('diaries.show', $draft))
            ->assertOk()
            ->assertSee('class="self-start rounded bg-zinc-900 px-5 py-2 text-white"', false)
            ->assertSee('href="'.route('diaries.edit', $draft).'">編集</a>', false);
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
