<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertOk();
    }

    public function test_password_can_be_confirmed_when_login_field_is_not_a_database_column(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user)->post(route('password.confirm.store'), [
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertIsInt(session('auth.password_confirmed_at'));
    }

    public function test_incorrect_password_is_not_confirmed(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user)->post(route('password.confirm.store'), [
            'password' => 'incorrect-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertNull(session('auth.password_confirmed_at'));
    }
}
