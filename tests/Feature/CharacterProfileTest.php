<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use App\Support\AvatarUrl;
use Database\Seeders\FfxiMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CharacterProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FfxiMasterSeeder::class);
    }

    public function test_user_can_create_character_with_ffxi_levels(): void
    {
        $user = User::factory()->create();
        $world = DB::table('ffxi_worlds')->first();
        $job = DB::table('ffxi_jobs')->first();
        $craft = DB::table('ffxi_crafts')->first();

        $this->actingAs($user)->get(route('characters.create'))->assertOk();

        $this->post(route('characters.store'), [
            'name' => 'Altana', 'visibility' => 'public', 'world_id' => $world->id,
            'job_levels' => [$job->id => 99], 'craft_levels' => [$craft->id => 110],
        ])->assertSessionHasNoErrors();

        $character = Character::query()->sole();
        $this->assertTrue($character->is_primary);
        $this->assertDatabaseHas('ffxi_profiles', ['character_id' => $character->id, 'world_id' => $world->id]);
        $this->assertDatabaseHas('ffxi_character_job_levels', ['character_id' => $character->id, 'job_id' => $job->id, 'level' => 99]);
        $this->assertDatabaseHas('ffxi_character_craft_levels', ['character_id' => $character->id, 'craft_id' => $craft->id, 'level' => 110]);
    }

    public function test_setting_new_primary_unsets_previous_primary(): void
    {
        $user = User::factory()->create();
        $gameId = DB::table('games')->where('code', 'ffxi')->value('id');
        $first = Character::create(['user_id' => $user->id, 'game_id' => $gameId, 'name' => 'First', 'is_primary' => true, 'visibility' => 'public']);
        $second = Character::create(['user_id' => $user->id, 'game_id' => $gameId, 'name' => 'Second', 'is_primary' => false, 'visibility' => 'public']);

        $this->actingAs($user)->put(route('characters.update', $second), ['name' => 'Second', 'visibility' => 'public', 'is_primary' => '1'])->assertSessionHasNoErrors();

        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($second->refresh()->is_primary);
    }

    public function test_private_character_is_only_visible_to_owner_or_admin(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $gameId = DB::table('games')->where('code', 'ffxi')->value('id');
        $character = Character::create(['user_id' => $owner->id, 'game_id' => $gameId, 'name' => 'Private', 'is_primary' => true, 'visibility' => 'private']);

        $this->get(route('characters.show', $character))->assertForbidden();
        $this->actingAs($other)->get(route('characters.show', $character))->assertForbidden();
        $this->actingAs($owner)->get(route('characters.show', $character))->assertOk();
    }

    public function test_member_cannot_edit_another_members_character(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $gameId = DB::table('games')->where('code', 'ffxi')->value('id');
        $character = Character::create(['user_id' => $owner->id, 'game_id' => $gameId, 'name' => 'Owned', 'is_primary' => true, 'visibility' => 'public']);

        $this->actingAs($other)->get(route('characters.edit', $character))->assertForbidden();
    }

    public function test_character_search_returns_one_row_per_visible_character(): void
    {
        $owner = User::factory()->create();
        $gameId = DB::table('games')->where('code', 'ffxi')->value('id');
        Character::create(['user_id' => $owner->id, 'game_id' => $gameId, 'name' => 'Visible Hero', 'short_message' => '冒険中', 'visibility' => 'public']);
        Character::create(['user_id' => $owner->id, 'game_id' => $gameId, 'name' => 'Hidden Hero', 'visibility' => 'private']);

        $this->get(route('characters.index', ['short_message' => '冒険']))
            ->assertOk()
            ->assertSee('Visible Hero')
            ->assertDontSee('Hidden Hero');
    }

    public function test_admin_can_bulk_update_selected_characters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create();
        $gameId = DB::table('games')->where('code', 'ffxi')->value('id');
        $characters = collect(['First', 'Second'])->map(fn (string $name) => Character::create(['user_id' => $owner->id, 'game_id' => $gameId, 'name' => $name, 'visibility' => 'public']));

        $this->actingAs($admin)->patch(route('admin.characters.bulk'), [
            'character_ids' => $characters->pluck('id')->all(),
            'field' => 'short_message',
            'value' => '一括更新',
        ])->assertSessionHasNoErrors();

        foreach ($characters as $character) {
            $this->assertDatabaseHas('characters', ['id' => $character->id, 'short_message' => '一括更新']);
        }
    }

    public function test_character_avatar_url_reads_attachment_from_eloquent_model(): void
    {
        $attachmentId = (string) Str::ulid();
        $character = new Character(['avatar_attachment_id' => $attachmentId]);

        $this->assertSame(route('images.show', [$attachmentId, 'thumbnail']), app(AvatarUrl::class)->character($character));
    }

    public function test_admin_can_update_character_without_changing_updated_at(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create();
        $character = Character::create(['user_id' => $owner->id, 'game_id' => DB::table('games')->where('code', 'ffxi')->value('id'), 'name' => 'Before', 'visibility' => 'public']);
        $original = $character->updated_at;
        $this->travel(1)->day();

        $this->actingAs($admin)->put(route('characters.update', $character), [
            'name' => 'After', 'visibility' => 'public', 'preserve_updated_at' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('After', $character->fresh()->name);
        $this->assertTrue($original->equalTo($character->fresh()->updated_at));
    }
}
