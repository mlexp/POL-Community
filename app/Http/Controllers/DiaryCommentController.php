<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use App\Models\DiaryComment;
use App\Support\ContentSanitizer;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DiaryCommentController extends Controller
{
    public function store(Request $request, Diary $diary, ContentSanitizer $sanitizer): RedirectResponse
    {
        abort_unless($diary->status === 'published' && $diary->comments_enabled && app(SiteSettings::class)->boolean('comments.enabled', true), 403);
        $data = $request->validate(['body_html' => ['required', 'string', 'max:5000'], 'parent_id' => ['nullable', 'string', Rule::exists('diary_comments', 'id')->where(fn ($query) => $query->where('diary_id', $diary->id)->where('status', 'visible')->whereNull('deleted_at'))]]);
        $body = $sanitizer->sanitize($data['body_html']);
        if (trim(strip_tags($body)) === '') {
            throw ValidationException::withMessages(['body_html' => 'コメントを入力してください。']);
        }
        DiaryComment::create(['diary_id' => $diary->id, 'user_id' => $request->user()->id, 'parent_id' => $data['parent_id'] ?? null, 'author_name_snapshot' => $request->user()->display_name, 'body_html' => $body, 'status' => 'visible']);

        return redirect()->route('diaries.show', $diary)->with('status', 'コメントを投稿しました。');
    }

    public function destroy(DiaryComment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);
        $diaryId = $comment->diary_id;
        $comment->delete();

        return redirect()->route('diaries.show', $diaryId)->with('status', 'コメントを削除しました。');
    }
}
