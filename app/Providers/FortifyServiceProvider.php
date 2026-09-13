<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::confirmPasswordsUsing(fn (User $user, ?string $password): bool => $user->password !== null
            && Hash::check((string) $password, $user->password));

        Fortify::authenticateUsing(function (Request $request): ?User {
            $login = Str::lower((string) $request->input('login'));
            $user = User::query()
                ->where('status', 'active')
                ->where(function ($query) use ($login): void {
                    $query->where('login_id', $login)->orWhere('email', $login);
                })
                ->first();

            return $user !== null
                && $user->password !== null
                && Hash::check((string) $request->input('password'), $user->password)
                    ? $user
                    : null;
        });

        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::registerView(function () {
            abort_unless(app(SiteSettings::class)->boolean('registration.enabled', true), 404);

            return view('pages::auth.register');
        });
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
        RateLimiter::for('login', function (Request $request) {
            $key = Str::transliterate(Str::lower((string) $request->input('login')).'|'.$request->ip());

            return Limit::perMinute(5)->by($key);
        });
        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(($credentialId ?: $request->session()->getId()).'|'.$request->ip());
        });
    }
}
