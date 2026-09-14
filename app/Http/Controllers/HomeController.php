<?php

namespace App\Http\Controllers;

use App\Services\ContentSearch;
use App\Support\ContentAccess;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request, ContentAccess $access, SiteSettings $settings): View
    {
        $limit = fn (string $key): int => max(1, min(100, (int) $settings->get($key, 10)));

        $configuredSections = $settings->get('home.sections', ['announcements', 'diaries', 'community', 'feeds']);
        if (! is_array($configuredSections)) {
            $configuredSections = ['announcements', 'diaries', 'community', 'feeds'];
        }
        $sections = array_values(array_unique(array_filter(
            $configuredSections,
            fn (mixed $section): bool => is_string($section) && in_array($section, ['announcements', 'diaries', 'community', 'feeds'], true),
        )));

        return view('content.home', [
            'sections' => $sections,
            'announcements' => $access->announcements($request->user())->latest('published_at')->limit($limit('home.announcement_limit'))->get(),
            'diaries' => $access->diaries($request->user())->join('users', 'diaries.user_id', '=', 'users.id')->whereNull('users.deleted_at')->latest('diaries.published_at')->limit($limit('home.diary_limit'))->get(['diaries.*', 'users.display_name as author_name']),
            'threads' => $access->threads($request->user())->leftJoin('community_posts as latest_post', function ($join) {
                $join->on('latest_post.thread_id', '=', 'community_threads.id')->on('latest_post.created_at', '=', 'community_threads.last_posted_at')->whereNull('latest_post.deleted_at')->where('latest_post.status', 'visible');
            })->latest('community_threads.last_posted_at')->limit($limit('home.community_limit'))->get(['community_threads.*', 'latest_post.user_id as latest_author_user_id', 'latest_post.author_name_snapshot as latest_author']),
            'feeds' => $access->visibility(DB::table('feed_items')->join('feed_sources', 'feed_items.feed_source_id', '=', 'feed_sources.id')->where('feed_sources.is_enabled', true), $request->user(), 'feed_sources.visibility')->orderByDesc('feed_items.published_at')->limit($limit('home.feed_item_limit'))->get(['feed_items.*', 'feed_sources.name as source_name']),
            'banners' => DB::table('banners')->join('attachments', 'banners.image_attachment_id', '=', 'attachments.id')->whereNull('attachments.deleted_at')->where('attachments.status', 'ready')->where('attachments.visibility', 'public')->where('banners.is_enabled', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->orderBy('sort_order')->get(['banners.*']),
            'header' => DB::table('site_assets')->join('attachments', 'site_assets.attachment_id', '=', 'attachments.id')->where('slot', 'header')->whereNull('attachments.deleted_at')->where('attachments.status', 'ready')->where('attachments.visibility', 'public')->first(['site_assets.*']),
        ]);
    }

    public function search(Request $request, ContentSearch $search): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'types' => ['nullable', 'array'], 'types.*' => ['string', 'in:diary,community,announcement']]);
        $term = trim($data['q'] ?? '');
        $types = $data['types'] ?? ['diary', 'community', 'announcement'];
        if ($types === []) {
            $types = ['diary', 'community', 'announcement'];
        }

        return view('content.search', ['term' => $term, 'types' => $types, 'results' => $term === '' ? null : $search->query($term, $request->user(), $types)->paginate(20)->withQueryString()]);
    }

    public function announcement(Request $request, string $id, ContentAccess $access): View
    {
        return view('content.announcement', ['announcement' => $access->announcements($request->user())->where('id', $id)->firstOrFail()]);
    }
}
