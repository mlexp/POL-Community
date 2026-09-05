<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RequiredPasswordController extends Controller
{
    use PasswordValidationRules;

    public function edit(Request $request): View|RedirectResponse
    {
        return $request->user()->password_reset_required
            ? view('auth.change-required-password')
            : redirect()->route('dashboard');
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => $this->passwordRules(),
        ]);

        if (! Hash::check($validated['current_password'], (string) $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => __('The provided password does not match your current password.')]);
        }

        $request->user()->forceFill(['password' => $validated['password'], 'password_reset_required' => false])->save();
        $request->session()->regenerate();
        $audit->log($request, 'user.required_password_changed', $request->user());

        return redirect()->route('dashboard')->with('status', 'パスワードを変更しました。');
    }
}
