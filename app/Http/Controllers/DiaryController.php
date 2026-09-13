<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use App\Services\ContentMedia;
use App\Services\ImageUpload;
use App\Support\ContentAccess;
use App\Support\ContentSanitizer;
use App\Support\SiteSettings;
use App\Support\UpdateTimestamp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DiaryController extends Controller
{
    public function index(Request $request): View
    {
        $query = Diary::query()->with(['user', 'character'])->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
        ! app(ContentAccess::class)->member($request->user()) ? $query->where('visibility', 'public') : $query->whereIn('visibility', ['public', 'members']);

        return view('diaries.index', ['diaries' => $query->latest('published_at')->paginate(20), 'mine' => false]);
    }

    public function mine(Request $request): View
    {
        return view('diaries.mine', ['diaries' => Diary::query()->with('character')->whereBelongsTo($request->user())->latest('updated_at')->paginate(20), 'mine' => true]);
    }

    public function create(Request $request): View
    {
        return view('diaries.form', ['diary' => null, ...$this->formData($request)]);
    }

    public function store(Request $request, ContentSanitizer $sanitizer, ContentMedia $media, ImageUpload $upload): RedirectResponse
    {
        $data = $this->validated($request, $sanitizer);
        $mediaData = $media->validatePending($request);
        $diary = DB::transaction(function () use ($request, $data, $mediaData, $media, $upload): Diary {
            $diary = Diary::create(['user_id' => $request->user()->id, ...$this->diaryValues($data)]);
            $this->syncTaxonomy($diary, $data);
            $media->attachPending($mediaData, $request->user(), 'diary', $diary->id, $diary->visibility, $upload);

            return $diary;
        });

        return redirect()->route('diaries.show', $diary)->with('status', '日記を保存しました。');
    }

    public function show(Request $request, Diary $diary): View
    {
        $isOwnerOrAdmin = $request->user() !== null && ($request->user()->id === $diary->user_id || $request->user()->role === 'admin');
        abort_unless($isOwnerOrAdmin || ($diary->status === 'published' && $diary->published_at?->isPast()), 404);
        abort_if(! $isOwnerOrAdmin && $diary->visibility === 'private', 403);
        abort_if(! $isOwnerOrAdmin && $diary->visibility === 'members' && $request->user() === null, 403);

        Gate::authorize('view', $diary);

        return view('diaries.show', ['diary' => $diary->load(['user', 'character']), 'categories' => DB::table('diary_categories')->join('diary_category', 'diary_categories.id', '=', 'diary_category.diary_category_id')->where('diary_category.diary_id', $diary->id)->get(['diary_categories.*']), 'tags' => DB::table('tags')->join('diary_tag', 'tags.id', '=', 'diary_tag.tag_id')->where('diary_tag.diary_id', $diary->id)->get(['tags.*']), 'comments' => DB::table('diary_comments')->where('diary_id', $diary->id)->where('status', 'visible')->whereNull('deleted_at')->orderBy('created_at')->get(), 'commentsGloballyEnabled' => app(SiteSettings::class)->boolean('comments.enabled', true)]);
    }

    public function edit(Request $request, Diary $diary): View
    {
        Gate::authorize('update', $diary);

        return view('diaries.form', ['diary' => $diary, ...$this->formData($request), 'selectedCategories' => DB::table('diary_category')->where('diary_id', $diary->id)->pluck('diary_category_id')->all(), 'tagText' => DB::table('tags')->join('diary_tag', 'tags.id', '=', 'diary_tag.tag_id')->where('diary_tag.diary_id', $diary->id)->orderBy('tags.name')->pluck('tags.name')->implode(', ')]);
    }

    public function update(Request $request, Diary $diary, ContentSanitizer $sanitizer, ContentMedia $media, ImageUpload $upload, UpdateTimestamp $timestamp): RedirectResponse
    {
        Gate::authorize('update', $diary);
        $data = $this->validated($request, $sanitizer);
        $mediaData = $media->validatePending($request);
        DB::transaction(function () use ($request, $diary, $data, $mediaData, $media, $upload, $timestamp): void {
            $timestamp->update($request, $diary, $this->diaryValues($data));
            $this->syncTaxonomy($diary, $data);
            $media->attachPending($mediaData, $request->user(), 'diary', $diary->id, $diary->visibility, $upload);
        });

        return redirect()->route('diaries.show', $diary)->with('status', '日記を更新しました。');
    }

    public function destroy(Diary $diary): RedirectResponse
    {
        Gate::authorize('delete', $diary);
        $diary->delete();

        return redirect()->route('diaries.mine')->with('status', '日記を削除しました。');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request): array
    {
        return ['characters' => DB::table('characters')->where('user_id', $request->user()->id)->whereNull('deleted_at')->orderByDesc('is_primary')->get(), 'categories' => DB::table('diary_categories')->orderBy('sort_order')->get(), 'selectedCategories' => [], 'tagText' => ''];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ContentSanitizer $sanitizer): array
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'body_html' => ['required', 'string', 'max:20000'], 'character_id' => ['nullable', 'string', Rule::exists('characters', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)->whereNull('deleted_at'))], 'visibility' => ['required', Rule::in(['public', 'members', 'private'])], 'status' => ['required', Rule::in(['draft', 'published', 'archived'])], 'comments_enabled' => ['required', 'boolean'], 'category_ids' => ['nullable', 'array', 'max:10'], 'category_ids.*' => ['integer', 'exists:diary_categories,id'], 'tags' => ['nullable', 'string', 'max:500']]);
        $data['body_html'] = $sanitizer->sanitize($data['body_html']);
        if (trim(strip_tags($data['body_html'])) === '') {
            throw ValidationException::withMessages(['body_html' => '本文を入力してください。']);
        }
        $data['comments_enabled'] = $request->boolean('comments_enabled');

        return $data;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function diaryValues(array $data): array
    {
        $plain = preg_replace('/\s+/u', ' ', strip_tags($data['body_html'])) ?? '';

        return ['character_id' => $data['character_id'] ?? null, 'title' => $data['title'], 'body_json' => ['type' => 'doc', 'format' => 'sanitized_html', 'html' => $data['body_html']], 'body_html' => $data['body_html'], 'excerpt' => Str::limit(trim($plain), 500), 'visibility' => $data['visibility'], 'status' => $data['status'], 'published_at' => $data['status'] === 'published' ? now() : null, 'comments_enabled' => $data['comments_enabled']];
    }

    /** @param array<string, mixed> $data */
    private function syncTaxonomy(Diary $diary, array $data): void
    {
        DB::table('diary_category')->where('diary_id', $diary->id)->delete();
        $categoryIds = array_values(array_unique(array_map('intval', is_array($data['category_ids'] ?? null) ? $data['category_ids'] : [])));
        if ($categoryIds === []) {
            $uncategorized = DB::table('diary_categories')->where('slug', 'uncategorized')->value('id');
            $categoryIds = $uncategorized === null ? [] : [(int) $uncategorized];
        }
        DB::table('diary_category')->insert(array_map(fn (int $id) => ['diary_id' => $diary->id, 'diary_category_id' => $id], $categoryIds));

        DB::table('diary_tag')->where('diary_id', $diary->id)->delete();
        $names = collect(preg_split('/[,、]/u', (string) ($data['tags'] ?? '')) ?: [])->map(fn (string $name) => trim($name))->filter()->unique()->take(10);
        foreach ($names as $name) {
            $slug = Str::slug($name) ?: 'tag-'.substr(hash('sha256', $name), 0, 16);
            $tagId = DB::table('tags')->where('slug', $slug)->value('id');
            if ($tagId === null) {
                $tagId = DB::table('tags')->insertGetId(['name' => $name, 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('diary_tag')->insert(['diary_id' => $diary->id, 'tag_id' => $tagId]);
        }
    }
}
