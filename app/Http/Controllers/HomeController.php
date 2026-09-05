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

        return view('content.home', [
            'announcements' => $access->announcements($request->user())->latest('published_at')->limit($limit('home.announcement_limit'))->get(),
            'diaries' => $access->diaries($request->user())->latest('published_at')->limit($limit('home.diary_limit'))->get(),
            'threads' => $access->threads($request->user())->latest('last_posted_at')->limit(10)->get(),
            'feeds' => $access->visibility(DB::table('feed_items')->join('feed_sources', 'feed_items.feed_source_id', '=', 'feed_sources.id')->where('feed_sources.is_enabled', true), $request->user(), 'feed_sources.visibility')->orderByDesc('feed_items.published_at')->limit($limit('home.feed_item_limit'))->get(['feed_items.*', 'feed_sources.name as source_name']),
            'banners' => DB::table('banners')->join('attachments', 'banners.image_attachment_id', '=', 'attachments.id')->whereNull('attachments.deleted_at')->where('attachments.status', 'ready')->where('attachments.visibility', 'public')->where('banners.is_enabled', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->orderBy('sort_order')->get(['banners.*']),
            'header' => DB::table('site_assets')->join('attachments', 'site_assets.attachment_id', '=', 'attachments.id')->where('slot', 'header')->whereNull('attachments.deleted_at')->where('attachments.status', 'ready')->where('attachments.visibility', 'public')->first(['site_assets.*']),
        ]);
    }

    public function search(Request $request, ContentSearch $search): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim($data['q'] ?? '');

        return view('content.search', ['term' => $term, 'results' => $term === '' ? null : $search->query($term, $request->user())->paginate(20)->withQueryString()]);
    }

    public function announcement(Request $request, string $id, ContentAccess $access): View
    {
        return view('content.announcement', ['announcement' => $access->announcements($request->user())->where('id', $id)->firstOrFail()]);
    }
}
