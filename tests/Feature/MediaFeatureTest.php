<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\CommunityPost;
use App\Models\CommunityThread;
use App\Models\Diary;
use App\Models\User;
use Database\Seeders\SiteDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiteDefaultsSeeder::class);
        Storage::fake('local');
    }

    public function test_image_is_reencoded_and_thumbnail_served_only_when_parent_is_visible(): void
    {
        $user = User::factory()->create();
        $diary = $this->diary($user);
        $this->actingAs($user)->post(route('media.store', ['diary', $diary->id]), ['kind' => 'image', 'image' => UploadedFile::fake()->image('photo.png', 1000, 500)])->assertSessionHasNoErrors()->assertRedirect();
        $image = Attachment::sole();
        $this->assertSame('image/webp', $image->mime_type);
        $this->assertStringStartsWith('users/'.$user->id.'/images/', $image->path);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($image->path)), $image->sha256);
        $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('local')->get($image->path))['mime']);
        $variant = DB::table('attachment_variants')->sole();
        $this->assertSame(480, (int) $variant->width);
        $this->assertSame(240, (int) $variant->height);
        $this->get(route('media.edit', ['diary', $diary->id]))->assertOk();
        auth()->logout();
        $this->get(route('images.show', $image))->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('images.show', [$image, 'thumbnail']))->assertOk();
        $this->get(route('images.show', [$image, 'bogus']))->assertNotFound();
        $diary->update(['visibility' => 'private']);
        $this->get(route('images.show', $image))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('images.show', $image))->assertForbidden();
        $diary->update(['visibility' => 'members']);
        $this->get(route('images.show', $image))->assertOk();
        $diary->delete();
        $this->get(route('images.show', $image))->assertForbidden();
    }

    public function test_upload_rejects_svg_fake_images_size_and_pixel_limits_and_other_owners(): void
    {
        $owner = User::factory()->create();
        $diary = $this->diary($owner);
        $this->actingAs(User::factory()->create())->post(route('media.store', ['diary', $diary->id]), ['kind' => 'image', 'image' => UploadedFile::fake()->image('x.png')])->assertForbidden();
        $this->actingAs($owner);
        foreach ([UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), UploadedFile::fake()->createWithContent('bad.jpg', '<?php echo 1;'), UploadedFile::fake()->image('huge.jpg')->size(1100)] as $file) {
            $this->post(route('media.store', ['diary', $diary->id]), ['kind' => 'image', 'image' => $file])->assertSessionHasErrors('image');
        }
        config(['content.image_max_pixels' => 100]);
        $this->post(route('media.store', ['diary', $diary->id]), ['kind' => 'image', 'image' => UploadedFile::fake()->image('pixels.png', 20, 20)])->assertSessionHasErrors('image');
        $this->assertDatabaseCount('attachments', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_external_images_and_video_are_explicit_validated_and_not_raw_iframes(): void
    {
        $user = User::factory()->create();
        $diary = $this->diary($user);
        $this->actingAs($user)->post(route('media.store', ['diary', $diary->id]), ['kind' => 'external', 'url' => 'https://example.com/a.png'])->assertSessionHasNoErrors()->assertRedirect();
        foreach (['javascript:alert(1)', 'http://example.com/a.png', 'https://user:pass@example.com/a.png'] as $url) {
            $this->post(route('media.store', ['diary', $diary->id]), ['kind' => 'external', 'url' => $url])->assertSessionHasErrors('url');
        }
        $this->post(route('media.store', ['diary', $diary->id]), ['kind' => 'video', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('media.store', ['diary', $diary->id]), ['kind' => 'video', 'url' => 'https://www.nicovideo.jp/watch/sm12345'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('media.store', ['diary', $diary->id]), ['kind' => 'video', 'url' => 'https://www.youtube.com.evil.example/watch?v=dQw4w9WgXcQ'])->assertSessionHasErrors('url');
        $response = $this->get(route('diaries.show', $diary))->assertOk()->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)->assertSee('https://embed.nicovideo.jp/watch/sm12345', false)->assertSee('referrerpolicy="no-referrer"', false);
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('frame-src https://www.youtube-nocookie.com https://embed.nicovideo.jp', $response->headers->get('Content-Security-Policy'));
        $videoId = DB::table('video_embeds')->where('provider', 'youtube')->value('id');
        $this->delete(route('media.destroy', ['diary', $diary->id, 'video', $videoId]))->assertRedirect();
        $this->get(route('diaries.show', $diary))->assertDontSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_hidden_community_post_revokes_image_access_and_media_cannot_be_attached_by_others(): void
    {
        $owner = User::factory()->create();
        $thread = CommunityThread::create(['created_by_user_id' => $owner->id, 'category_id' => DB::table('community_categories')->value('id'), 'title' => '画像', 'status' => 'open', 'visibility' => 'public', 'last_posted_at' => now()]);
        $post = CommunityPost::create(['thread_id' => $thread->id, 'user_id' => $owner->id, 'body_html' => '本文', 'status' => 'visible']);
        $this->actingAs($owner)->post(route('media.store', ['community_post', $post->id]), ['kind' => 'image', 'image' => UploadedFile::fake()->image('x.jpg')])->assertRedirect();
        $image = Attachment::sole();
        auth()->logout();
        $this->get(route('images.show', $image))->assertOk();
        $post->update(['status' => 'hidden']);
        $this->get(route('images.show', $image))->assertForbidden();
    }

    public function test_admin_header_and_banner_uploads_and_home_visibility(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.images.store'), ['image' => UploadedFile::fake()->image('header.png'), 'slot' => 'header', 'alt_text' => 'サイトヘッダー'])->assertRedirect();
        $image = Attachment::sole();
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('サイトヘッダー');
        $this->get(route('images.show', $image))->assertOk();
        $this->actingAs($admin)->post(route('admin.banners.store'), ['name' => 'バナー', 'image_attachment_id' => $image->id, 'destination_url' => 'https://example.com', 'alt_text' => '広告テスト', 'placement' => 'home_sidebar', 'sort_order' => 0, 'is_enabled' => 1, 'rel_sponsored' => 1])->assertRedirect();
        $this->get('/')->assertSee('広告テスト')->assertSee('noopener noreferrer sponsored');
        DB::table('banners')->update(['starts_at' => now()->addDay()]);
        $this->get('/')->assertDontSee('広告テスト');
    }

    private function diary(User $user): Diary
    {
        return Diary::create(['user_id' => $user->id, 'title' => '日記', 'body_html' => '<p>本文</p>', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()->subMinute()]);
    }
}
