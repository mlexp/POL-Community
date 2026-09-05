<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_is_hidden_when_disabled(): void
    {
        config(['setup.enabled' => false, 'setup.token' => str_repeat('a', 32)]);

        $this->get(route('setup.create'))->assertNotFound();
    }

    public function test_invalid_setup_token_is_rejected(): void
    {
        config(['setup.enabled' => true, 'setup.token' => str_repeat('a', 32)]);

        $this->post(route('setup.store'), $this->payload(['setup_token' => str_repeat('b', 32)]))
            ->assertForbidden();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('application_initializations', 0);
    }

    public function test_initial_admin_can_only_be_created_once(): void
    {
        config(['setup.enabled' => true, 'setup.token' => str_repeat('a', 32)]);

        $this->post(route('setup.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'login_id' => 'initial-admin',
            'role' => 'admin',
            'status' => 'active',
            'password_reset_required' => true,
        ]);
        $this->assertDatabaseHas('application_initializations', ['id' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.initialized']);
        $this->get(route('setup.create'))->assertNotFound();
    }

    /** @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return [...[
            'setup_token' => str_repeat('a', 32),
            'login_id' => 'initial-admin',
            'display_name' => 'Initial Admin',
            'email' => 'admin@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ], ...$overrides];
    }
}
