<?php

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use App\Models\CommunityThread;
use App\Services\ContentMedia;
use App\Services\ImageUpload;
use App\Support\AuditLogger;
use App\Support\ContentAccess;
use App\Support\ContentSanitizer;
use App\Support\UpdateTimestamp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommunityController extends Controller
{
    public function index(Request $request, ContentAccess $access): View
    {
        $data = $request->validate(['category' => ['nullable', 'integer'], 'mine' => ['nullable', 'boolean']]);
        $query = $access->threads($request->user());
        if ($request->boolean('mine')) {
            abort_unless($access->member($request->user()), 403);
            $query = DB::table('community_threads')->whereNull('deleted_at')->where('created_by_user_id', $request->user()->id);
        }
        if (isset($data['category'])) {
            $query->where('category_id', $data['category']);
        }
        $firstPost = fn () => DB::table('community_posts')->select('body_html')->whereColumn('thread_id', 'community_threads.id')->where('status', 'visible')->whereNull('deleted_at')->orderBy('created_at')->orderBy('id')->limit(1);
        $firstPostId = fn () => DB::table('community_posts')->select('id')->whereColumn('thread_id', 'community_threads.id')->where('status', 'visible')->whereNull('deleted_at')->orderBy('created_at')->orderBy('id')->limit(1);
        $firstPostAuthor = fn () => DB::table('community_posts')->select('author_name_snapshot')->whereColumn('thread_id', 'community_threads.id')->where('status', 'visible')->whereNull('deleted_at')->orderBy('created_at')->orderBy('id')->limit(1);
        $query->addSelect(['preview_body_html' => $firstPost(), 'preview_post_id' => $firstPostId(), 'thread_author' => $firstPostAuthor()]);

        return view('community.index', ['threads' => $query->orderByDesc('is_pinned')->orderByDesc('last_posted_at')->orderByDesc('id')->paginate(20)->withQueryString(), 'categories' => DB::table('community_categories')->orderBy('sort_order')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', CommunityThread::class);

        return view('community.form', ['thread' => null, 'categories' => DB::table('community_categories')->where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function store(Request $request, ContentMedia $media, ImageUpload $upload): RedirectResponse
    {
        Gate::authorize('create', CommunityThread::class);
        $data = $this->threadData($request);
        $body = $this->body($request);
        $mediaData = $media->validatePending($request);
        $thread = DB::transaction(function () use ($request, $data, $body, $mediaData, $media, $upload) {
            $thread = CommunityThread::create([...$data, 'created_by_user_id' => $request->user()->id, 'status' => 'open', 'last_posted_at' => now(), 'post_count' => 1]);
            $post = CommunityPost::create(['thread_id' => $thread->id, 'user_id' => $request->user()->id, 'author_name_snapshot' => $request->user()->display_name, 'body_html' => $body, 'status' => 'visible']);
            $media->attachPending($mediaData, $request->user(), 'community_post', $post->id, $thread->visibility, $upload);

            return $thread;
        });

        return redirect()->route('community.show', $thread);
    }

    public function show(CommunityThread $thread): View
    {
        Gate::authorize('view', $thread);

        return view('community.show', ['thread' => $thread, 'posts' => CommunityPost::where('thread_id', $thread->id)->where('status', 'visible')->orderBy('created_at')->orderBy('id')->paginate(20)]);
    }

    public function edit(CommunityThread $thread): View
    {
        Gate::authorize('update', $thread);

        return view('community.form', ['thread' => $thread, 'categories' => DB::table('community_categories')->where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function update(Request $request, CommunityThread $thread, AuditLogger $audit, UpdateTimestamp $timestamp): RedirectResponse
    {
        Gate::authorize('update', $thread);
        $data = $this->threadData($request);
        $data += $request->validate(['status' => ['required', Rule::in($request->user()->role === 'admin' ? ['open', 'closed', 'hidden'] : ['open', 'closed'])]]);
        if ($request->user()->role === 'admin') {
            $data['is_pinned'] = $request->boolean('is_pinned');
        }
        $timestamp->update($request, $thread, $data);
        $audit->log($request, 'community_thread.updated', $thread, null, $data);

        return redirect()->route('community.show', $thread);
    }

    public function destroy(Request $request, CommunityThread $thread, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $thread);
        $thread->delete();
        $audit->log($request, 'community_thread.deleted', $thread);

        return redirect()->route('community.index');
    }

    public function reply(Request $request, CommunityThread $thread, ContentMedia $media, ImageUpload $upload): RedirectResponse
    {
        Gate::authorize('reply', $thread);
        $body = $this->body($request);
        $mediaData = $media->validatePending($request);
        $reply = DB::transaction(function () use ($request, $thread, $body, $mediaData, $media, $upload): CommunityPost {
            $locked = CommunityThread::whereKey($thread->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('reply', $locked);
            $reply = CommunityPost::create(['thread_id' => $locked->id, 'user_id' => $request->user()->id, 'author_name_snapshot' => $request->user()->display_name, 'body_html' => $body, 'status' => 'visible']);
            $media->attachPending($mediaData, $request->user(), 'community_post', $reply->id, $locked->visibility, $upload);
            $this->recount($locked);

            return $reply;
        });

        $count = CommunityPost::where('thread_id', $thread->id)->where('status', 'visible')->count();

        return redirect()->to(route('community.show', [
            'thread' => $thread,
            'page' => (int) ceil($count / 20),
        ]).'#post-'.$reply->id);
    }

    public function editPost(CommunityPost $post): View
    {
        Gate::authorize('update', $post);

        return view('community.post', compact('post'));
    }

    public function updatePost(Request $request, CommunityPost $post, UpdateTimestamp $timestamp): RedirectResponse
    {
        Gate::authorize('update', $post);
        $timestamp->update($request, $post, ['body_html' => $this->body($request)]);

        return redirect()->route('community.show', $post->thread_id);
    }

    public function deletePost(Request $request, CommunityPost $post, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $post);
        DB::transaction(function () use ($post): void {
            $thread = CommunityThread::whereKey($post->thread_id)->lockForUpdate()->firstOrFail();
            $post->delete();
            $this->recount($thread);
        });
        $audit->log($request, 'community_post.deleted', $post);

        return redirect()->route('community.show', $post->thread_id);
    }

    private function recount(CommunityThread $thread): void
    {
        $posts = CommunityPost::where('thread_id', $thread->id)->where('status', 'visible');
        $thread->update(['post_count' => $posts->count(), 'last_posted_at' => $posts->max('created_at') ?? $thread->created_at]);
    }

    /** @return array<string, mixed> */
    private function threadData(Request $request): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:200'], 'category_id' => ['required', 'integer', Rule::exists('community_categories', 'id')->where('is_active', true)], 'visibility' => ['required', Rule::in(['public', 'members', 'private'])]]);
    }

    private function body(Request $request): string
    {
        $data = $request->validate(['body_html' => ['required', 'string', 'max:20000']]);
        $body = app(ContentSanitizer::class)->sanitize($data['body_html']);
        if (trim(strip_tags($body)) === '') {
            throw ValidationException::withMessages(['body_html' => '本文を入力してください。']);
        }

        return $body;
    }
}
