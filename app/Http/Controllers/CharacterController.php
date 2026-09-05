<?php

namespace App\Http\Controllers;

use App\Models\Character;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CharacterController extends Controller
{
    public function index(Request $request): View
    {
        return view('characters.index', ['characters' => Character::query()->whereBelongsTo($request->user())->orderByDesc('is_primary')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('characters.form', [...$this->masters(), 'character' => null, 'profile' => null, 'jobLevels' => [], 'craftLevels' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $character = DB::transaction(function () use ($request, $data): Character {
            $gameId = DB::table('games')->where('code', 'ffxi')->where('is_enabled', true)->value('id');
            abort_if($gameId === null, 503, 'FFXIマスタが初期化されていません。');
            $makePrimary = $request->boolean('is_primary') || ! Character::query()->whereBelongsTo($request->user())->where('game_id', $gameId)->exists();
            if ($makePrimary) {
                Character::query()->whereBelongsTo($request->user())->where('game_id', $gameId)->update(['is_primary' => false]);
            }
            $character = Character::create(['user_id' => $request->user()->id, 'game_id' => $gameId, 'name' => $data['name'], 'is_primary' => $makePrimary, 'profile_text' => $data['profile_text'] ?? null, 'visibility' => $data['visibility']]);
            $this->saveFfxiDetails($character, $data);

            return $character;
        });

        return redirect()->route('characters.show', $character)->with('status', 'キャラクターを登録しました。');
    }

    public function show(Request $request, Character $character): View
    {
        if ($character->visibility === 'members') {
            abort_unless($request->user() !== null, 403);
        }
        if ($character->visibility === 'private') {
            Gate::authorize('view', $character);
        }

        return view('characters.show', ['character' => $character, 'profile' => DB::table('ffxi_profiles')->where('character_id', $character->id)->first(), ...$this->masters(), 'jobLevels' => DB::table('ffxi_character_job_levels')->where('character_id', $character->id)->pluck('level', 'job_id'), 'craftLevels' => DB::table('ffxi_character_craft_levels')->where('character_id', $character->id)->pluck('level', 'craft_id')]);
    }

    public function edit(Character $character): View
    {
        Gate::authorize('update', $character);

        return view('characters.form', ['character' => $character, 'profile' => DB::table('ffxi_profiles')->where('character_id', $character->id)->first(), ...$this->masters(), 'jobLevels' => DB::table('ffxi_character_job_levels')->where('character_id', $character->id)->pluck('level', 'job_id')->all(), 'craftLevels' => DB::table('ffxi_character_craft_levels')->where('character_id', $character->id)->pluck('level', 'craft_id')->all()]);
    }

    public function update(Request $request, Character $character): RedirectResponse
    {
        Gate::authorize('update', $character);
        $data = $this->validated($request);
        DB::transaction(function () use ($request, $character, $data): void {
            if ($request->boolean('is_primary')) {
                Character::query()->where('user_id', $character->user_id)->where('game_id', $character->game_id)->where('id', '!=', $character->id)->update(['is_primary' => false]);
            }
            $character->update(['name' => $data['name'], 'is_primary' => $request->boolean('is_primary') || $character->is_primary, 'profile_text' => $data['profile_text'] ?? null, 'visibility' => $data['visibility']]);
            $this->saveFfxiDetails($character, $data);
        });

        return redirect()->route('characters.show', $character)->with('status', 'キャラクターを更新しました。');
    }

    public function destroy(Character $character): RedirectResponse
    {
        Gate::authorize('delete', $character);
        DB::transaction(function () use ($character): void {
            $wasPrimary = $character->is_primary;
            $character->delete();
            if ($wasPrimary) {
                Character::query()->where('user_id', $character->user_id)->where('game_id', $character->game_id)->oldest()->limit(1)->update(['is_primary' => true]);
            }
        });

        return redirect()->route('characters.index')->with('status', 'キャラクターを削除しました。');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'profile_text' => ['nullable', 'string', 'max:10000'], 'visibility' => ['required', Rule::in(['public', 'members', 'private'])], 'is_primary' => ['nullable', 'boolean'],
            'world_id' => ['nullable', 'integer', 'exists:ffxi_worlds,id'], 'nation_id' => ['nullable', 'integer', 'exists:ffxi_nations,id'], 'rank' => ['nullable', 'integer', 'between:0,10'], 'race_id' => ['nullable', 'integer', 'exists:ffxi_races,id'], 'face_type_id' => ['nullable', 'integer', 'exists:ffxi_face_types,id'], 'main_job_id' => ['nullable', 'integer', 'exists:ffxi_jobs,id'], 'support_job_id' => ['nullable', 'integer', 'exists:ffxi_jobs,id'], 'pol_handle' => ['nullable', 'string', 'max:64'],
            'job_levels' => ['nullable', 'array'], 'job_levels.*' => ['nullable', 'integer', 'between:0,99'], 'craft_levels' => ['nullable', 'array'], 'craft_levels.*' => ['nullable', 'integer', 'between:0,999'],
        ]);
        if (! empty($data['face_type_id']) && DB::table('ffxi_face_types')->where('id', $data['face_type_id'])->value('race_id') !== (int) ($data['race_id'] ?? 0)) {
            throw ValidationException::withMessages(['face_type_id' => 'フェイスタイプと種族が一致しません。']);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function saveFfxiDetails(Character $character, array $data): void
    {
        DB::table('ffxi_profiles')->updateOrInsert(['character_id' => $character->id], ['world_id' => $data['world_id'] ?? null, 'nation_id' => $data['nation_id'] ?? null, 'rank' => $data['rank'] ?? null, 'race_id' => $data['race_id'] ?? null, 'face_type_id' => $data['face_type_id'] ?? null, 'main_job_id' => $data['main_job_id'] ?? null, 'support_job_id' => $data['support_job_id'] ?? null, 'pol_handle' => $data['pol_handle'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ffxi_character_job_levels')->where('character_id', $character->id)->delete();
        DB::table('ffxi_character_craft_levels')->where('character_id', $character->id)->delete();
        $jobs = $this->levelRows($character, $data['job_levels'] ?? [], 'job_id');
        $crafts = $this->levelRows($character, $data['craft_levels'] ?? [], 'craft_id');
        if ($jobs !== []) {
            DB::table('ffxi_character_job_levels')->insert($jobs);
        }
        if ($crafts !== []) {
            DB::table('ffxi_character_craft_levels')->insert($crafts);
        }
    }

    /** @return array<int, array<string, int|string|\DateTimeInterface>> */
    private function levelRows(Character $character, mixed $levels, string $foreignKey): array
    {
        if (! is_array($levels)) {
            return [];
        }

        $rows = [];
        foreach ($levels as $id => $level) {
            if (($level === null || $level === '') || ! is_numeric($id) || ! is_numeric($level)) {
                continue;
            }
            $rows[] = ['character_id' => $character->id, $foreignKey => (int) $id, 'level' => (int) $level, 'updated_at' => now()];
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function masters(): array
    {
        return ['worlds' => DB::table('ffxi_worlds')->where('is_active', true)->orderBy('sort_order')->get(), 'nations' => DB::table('ffxi_nations')->where('is_active', true)->orderBy('sort_order')->get(), 'races' => DB::table('ffxi_races')->where('is_active', true)->orderBy('sort_order')->get(), 'faces' => DB::table('ffxi_face_types')->where('is_active', true)->orderBy('sort_order')->get(), 'jobs' => DB::table('ffxi_jobs')->where('is_active', true)->orderBy('sort_order')->get(), 'crafts' => DB::table('ffxi_crafts')->where('is_active', true)->orderBy('sort_order')->get()];
    }
}
