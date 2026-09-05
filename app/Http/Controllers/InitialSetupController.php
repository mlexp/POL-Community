<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInitialAdminRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InitialSetupController extends Controller
{
    public function create(): View
    {
        $this->ensureAvailable();

        return view('setup.create');
    }

    public function store(StoreInitialAdminRequest $request): RedirectResponse
    {
        $this->ensureAvailable();
        $token = config('setup.token');

        abort_unless(is_string($token) && strlen($token) >= 32, 404);
        abort_unless(hash_equals($token, (string) $request->validated('setup_token')), 403);

        DB::transaction(function () use ($request): void {
            DB::table('application_initializations')->insert([
                'id' => 1,
                'initialized_at' => now(),
                'admin_user_id' => null,
            ]);

            $admin = User::create([
                'login_id' => Str::lower((string) $request->validated('login_id')),
                'display_name' => $request->validated('display_name'),
                'email' => Str::lower((string) $request->validated('email')),
                'email_verified_at' => now(),
                'password' => $request->validated('password'),
                'password_reset_required' => true,
                'role' => 'admin',
                'status' => 'active',
            ]);

            DB::table('application_initializations')->where('id', 1)->update(['admin_user_id' => $admin->id]);
            DB::table('audit_logs')->insert([
                'id' => (string) Str::ulid(),
                'actor_user_id' => $admin->id,
                'action' => 'system.initialized',
                'subject_type' => User::class,
                'subject_id' => $admin->id,
                'created_at' => now(),
            ]);
        });

        return redirect()->route('login')->with('status', '初期管理者を作成しました。環境変数 INITIAL_SETUP_ENABLED を false にしてください。');
    }

    private function ensureAvailable(): void
    {
        abort_unless(config('setup.enabled'), 404);
        abort_if(app()->isProduction() && ! request()->secure(), 404);
        abort_if(DB::table('application_initializations')->where('id', 1)->exists(), 404);
        abort_if(User::withTrashed()->where('role', 'admin')->exists(), 404);
    }
}
