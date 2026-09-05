<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse;

class PendingRegistrationResponse implements RegisterResponse
{
    public function toResponse($request): RedirectResponse
    {
        if ($request->user()?->status === 'active') {
            return redirect()->intended(route('dashboard'));
        }

        Auth::guard(config('fortify.guard'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', '登録申請を受け付けました。管理者の承認後にログインできます。');
    }
}
