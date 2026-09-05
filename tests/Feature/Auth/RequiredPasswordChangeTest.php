<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequiredPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_password_change_blocks_other_authenticated_pages(): void
    {
        $user = User::factory()->create(['password_reset_required' => true]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.change-required.edit'));
        $this->actingAs($user)->get(route('profile.edit'))->assertRedirect(route('password.change-required.edit'));
        $this->actingAs($user)->get(route('password.change-required.edit'))->assertOk();
    }

    public function test_user_can_change_required_password(): void
    {
        $user = User::factory()->create(['password_reset_required' => true]);

        $this->actingAs($user)->put(route('password.change-required.update'), [
            'current_password' => 'password',
            'password' => 'a-new-secure-password',
            'password_confirmation' => 'a-new-secure-password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

        $this->assertFalse($user->refresh()->password_reset_required);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.required_password_changed', 'subject_id' => $user->id]);
    }
}
