<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Services\AttachmentCleanup;
use App\Services\ImageUpload;
use App\Support\ContentAccess;
use App\Support\SiteSettings;
use App\Support\UpdateTimestamp;
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
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'], 'world_id' => ['nullable', 'integer'], 'nation_id' => ['nullable', 'integer'],
            'rank' => ['nullable', 'integer', 'between:0,10'], 'short_message' => ['nullable', 'string', 'max:100'],
            'visibility' => ['nullable', Rule::in(['public', 'members', 'private'])],
            'sort' => ['nullable', Rule::in(['name', 'world', 'nation', 'rank', 'short_message', 'updated_at'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $user = $request->user();
        $allowed = $user?->role === 'admin' ? ['public', 'members', 'private'] : (app(ContentAccess::class)->member($user) ? ['public', 'members'] : ['public']);
        $query = DB::table('characters')->join('users', 'characters.user_id', '=', 'users.id')
            ->leftJoin('ffxi_profiles', 'characters.id', '=', 'ffxi_profiles.character_id')->leftJoin('ffxi_worlds', 'ffxi_profiles.world_id', '=', 'ffxi_worlds.id')->leftJoin('ffxi_nations', 'ffxi_profiles.nation_id', '=', 'ffxi_nations.id')
            ->whereNull('characters.deleted_at')->whereNull('users.deleted_at')
            ->where(fn ($q) => $q->whereIn('characters.visibility', $allowed)->when($user, fn ($q) => $q->orWhere('characters.user_id', $user->id)))
            ->when($data['name'] ?? null, fn ($q, $v) => $q->where('characters.name', 'like', '%'.$v.'%'))
            ->when($data['world_id'] ?? null, fn ($q, $v) => $q->where('ffxi_profiles.world_id', $v))->when($data['nation_id'] ?? null, fn ($q, $v) => $q->where('ffxi_profiles.nation_id', $v))
            ->when(array_key_exists('rank', $data), fn ($q) => $q->where('ffxi_profiles.rank', $data['rank']))
            ->when($data['short_message'] ?? null, fn ($q, $v) => $q->where('characters.short_message', 'like', '%'.$v.'%'))
            ->when(($user?->role === 'admin') && ($data['visibility'] ?? null), fn ($q, $v) => $q->where('characters.visibility', $v));
        $sort = ['name' => 'characters.name', 'world' => 'ffxi_worlds.name_en', 'nation' => 'ffxi_nations.name_ja', 'rank' => 'ffxi_profiles.rank', 'short_message' => 'characters.short_message', 'updated_at' => 'characters.updated_at'][$data['sort'] ?? 'updated_at'];

        return view('characters.index', [...$this->masters(), 'characters' => $query->select('characters.*', 'ffxi_profiles.world_id', 'ffxi_profiles.nation_id', 'ffxi_profiles.rank', 'ffxi_worlds.name_en as world_name', 'ffxi_nations.name_ja as nation_name', 'ffxi_nations.flag_path')->orderBy($sort, $data['direction'] ?? 'desc')->paginate(20)->withQueryString(), 'isAdmin' => $user?->role === 'admin']);
    }

    public function mine(Request $request): View
    {
        return view('characters.mine', ['characters' => Character::query()->whereBelongsTo($request->user())->orderByDesc('is_primary')->orderBy('name')->get()]);
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
            $character = Character::create(['user_id' => $request->user()->id, 'game_id' => $gameId, 'name' => $data['name'], 'is_primary' => $makePrimary, 'profile_text' => $data['profile_text'] ?? null, 'short_message' => $data['short_message'] ?? null, 'visibility' => $data['visibility']]);
            $this->saveFfxiDetails($character, $data);

            return $character;
        });

        return redirect()->route('characters.show', $character)->with('status', 'キャラクターを登録しました。');
    }

    public function show(Request $request, Character $character): View
    {
        if ($character->visibility === 'members') {
            abort_unless(app(ContentAccess::class)->member($request->user()), 403);
        }
        if ($character->visibility === 'private') {
            Gate::authorize('view', $character);
        }

        $diaries = app(ContentAccess::class)->diaries($request->user())->where('character_id', $character->id)->latest('published_at')->limit(max(1, min(100, (int) app(SiteSettings::class)->get('home.character_diary_limit', 5))))->get();

        return view('characters.show', ['character' => $character, 'profile' => DB::table('ffxi_profiles')->where('character_id', $character->id)->first(), ...$this->masters(), 'jobLevels' => DB::table('ffxi_character_job_levels')->where('character_id', $character->id)->pluck('level', 'job_id'), 'craftLevels' => DB::table('ffxi_character_craft_levels')->where('character_id', $character->id)->pluck('level', 'craft_id'), 'diaries' => $diaries]);
    }

    public function edit(Character $character): View
    {
        Gate::authorize('update', $character);

        return view('characters.form', ['character' => $character, 'profile' => DB::table('ffxi_profiles')->where('character_id', $character->id)->first(), ...$this->masters(), 'jobLevels' => DB::table('ffxi_character_job_levels')->where('character_id', $character->id)->pluck('level', 'job_id')->all(), 'craftLevels' => DB::table('ffxi_character_craft_levels')->where('character_id', $character->id)->pluck('level', 'craft_id')->all()]);
    }

    public function update(Request $request, Character $character, ImageUpload $upload, UpdateTimestamp $timestamp, AttachmentCleanup $cleanup): RedirectResponse
    {
        Gate::authorize('update', $character);
        $data = $this->validated($request);
        $oldAvatar = $character->avatar_attachment_id;
        $avatar = $request->hasFile('avatar') ? $upload->store($request->file('avatar'), $request->user(), $data['visibility'], 'avatar', 'avatar') : null;
        DB::transaction(function () use ($request, $character, $data, $avatar, $timestamp): void {
            if ($request->boolean('is_primary')) {
                Character::query()->where('user_id', $character->user_id)->where('game_id', $character->game_id)->where('id', '!=', $character->id)->update(['is_primary' => false]);
            }
            $timestamp->update($request, $character, ['name' => $data['name'], 'is_primary' => $request->boolean('is_primary') || $character->is_primary, 'profile_text' => $data['profile_text'] ?? null, 'short_message' => $data['short_message'] ?? null, 'avatar_attachment_id' => $request->boolean('remove_avatar') ? null : ($avatar !== null ? $avatar->id : $character->avatar_attachment_id), 'visibility' => $data['visibility']]);
            $this->saveFfxiDetails($character, $data, $timestamp->preserve($request));
        });
        if (($avatar !== null || $request->boolean('remove_avatar')) && $oldAvatar !== $character->fresh()->avatar_attachment_id) {
            $cleanup->deleteIfUnused($oldAvatar);
        }

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

    public function bulkUpdate(Request $request, UpdateTimestamp $timestamp): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $data = $request->validate(['character_ids' => ['required', 'array', 'max:100'], 'character_ids.*' => ['string', 'exists:characters,id'], 'field' => ['required', Rule::in(['name', 'world_id', 'nation_id', 'rank', 'short_message', 'visibility', 'delete'])], 'value' => ['nullable', 'string', 'max:280']]);
        $preserve = $timestamp->preserve($request);
        DB::transaction(function () use ($data, $preserve): void {
            $ids = $data['character_ids'];
            if ($data['field'] === 'delete') {
                Character::whereKey($ids)->delete();
            } elseif (in_array($data['field'], ['world_id', 'nation_id', 'rank'], true)) {
                foreach ($ids as $id) {
                    $profileValues = [$data['field'] => ($data['value'] ?? '') === '' ? null : (int) $data['value']];
                    if (! $preserve) {
                        $profileValues['updated_at'] = now();
                        DB::table('characters')->where('id', $id)->update(['updated_at' => now()]);
                    }
                    DB::table('ffxi_profiles')->where('character_id', $id)->update($profileValues);
                }
            } else {
                $values = [$data['field'] => ($data['value'] ?? '') === '' ? null : $data['value']];
                if (! $preserve) {
                    $values['updated_at'] = now();
                }
                Character::whereKey($ids)->update($values);
            }
        });

        return back()->with('status', '選択したキャラクターを更新しました。');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'profile_text' => ['nullable', 'string', 'max:10000'], 'short_message' => ['nullable', 'string', 'max:280'], 'visibility' => ['required', Rule::in(['public', 'members', 'private'])], 'is_primary' => ['nullable', 'boolean'],
            'world_id' => ['nullable', 'integer', 'exists:ffxi_worlds,id'], 'nation_id' => ['nullable', 'integer', 'exists:ffxi_nations,id'], 'rank' => ['nullable', 'integer', 'between:0,10'], 'race_id' => ['nullable', 'integer', 'exists:ffxi_races,id'], 'gender' => ['nullable', Rule::in(['male', 'female'])], 'face_type_id' => ['nullable', 'integer', 'exists:ffxi_face_types,id'], 'main_job_id' => ['nullable', 'integer', 'exists:ffxi_jobs,id'], 'support_job_id' => ['nullable', 'integer', 'exists:ffxi_jobs,id'], 'pol_handle' => ['nullable', 'string', 'max:64'], 'avatar' => ['nullable', 'file'], 'remove_avatar' => ['nullable', 'boolean'],
            'job_levels' => ['nullable', 'array'], 'job_levels.*' => ['nullable', 'integer', 'between:0,99'], 'craft_levels' => ['nullable', 'array'], 'craft_levels.*' => ['nullable', 'integer', 'between:0,999'],
        ]);
        if (! empty($data['face_type_id']) && ! DB::table('ffxi_face_types')->where('id', $data['face_type_id'])->where('race_id', (int) ($data['race_id'] ?? 0))->where('gender', $data['gender'] ?? '')->exists()) {
            throw ValidationException::withMessages(['face_type_id' => 'フェイスタイプと種族が一致しません。']);
        }
        if (! empty($data['race_id']) && ! empty($data['gender'])) {
            $race = DB::table('ffxi_races')->where('id', $data['race_id'])->value('code');
            if (($race === 'mithra' && $data['gender'] !== 'female') || ($race === 'galka' && $data['gender'] !== 'male')) {
                throw ValidationException::withMessages(['gender' => '選択した種族ではこの性別を設定できません。']);
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function saveFfxiDetails(Character $character, array $data, bool $preserveUpdatedAt = false): void
    {
        $profile = ['world_id' => $data['world_id'] ?? null, 'nation_id' => $data['nation_id'] ?? null, 'rank' => $data['rank'] ?? null, 'race_id' => $data['race_id'] ?? null, 'gender' => $data['gender'] ?? null, 'face_type_id' => $data['face_type_id'] ?? null, 'main_job_id' => $data['main_job_id'] ?? null, 'support_job_id' => $data['support_job_id'] ?? null, 'pol_handle' => $data['pol_handle'] ?? null];
        if (! $preserveUpdatedAt || ! DB::table('ffxi_profiles')->where('character_id', $character->id)->exists()) {
            $profile += ['created_at' => now(), 'updated_at' => now()];
        }
        DB::table('ffxi_profiles')->updateOrInsert(['character_id' => $character->id], $profile);
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
