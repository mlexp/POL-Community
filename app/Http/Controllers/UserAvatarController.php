<?php

namespace App\Http\Controllers;

use App\Services\ImageUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserAvatarController extends Controller
{
    public function update(Request $request, ImageUpload $upload): RedirectResponse
    {
        try {
            $request->validate(['avatar' => ['required', 'file']]);
            $avatar = $upload->store($request->file('avatar'), $request->user(), 'public', 'avatar', 'avatar');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['avatar'][0] ?? '画像をアップロードできませんでした。';

            return back()->withErrors($exception->errors())->with('avatar_error', $message);
        }
        $request->user()->update(['avatar_attachment_id' => $avatar->id]);

        return back()->with('status', 'アバターを更新しました。');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->update(['avatar_attachment_id' => null]);

        return back()->with('status', 'アバターをデフォルトへ戻しました。');
    }
}
