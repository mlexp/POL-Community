<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_data_is_complete_and_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('games', 1);
        $this->assertDatabaseCount('ffxi_worlds', 16);
        $this->assertDatabaseCount('ffxi_nations', 3);
        $this->assertDatabaseCount('ffxi_races', 5);
        $this->assertDatabaseCount('ffxi_jobs', 22);
        $this->assertDatabaseCount('ffxi_crafts', 10);
        $this->assertDatabaseCount('ffxi_face_types', 80);
        $this->assertDatabaseCount('community_categories', 5);
        $this->assertDatabaseCount('site_settings', 12);
        $this->assertDatabaseHas('ffxi_jobs', ['code' => 'sch', 'name_ja' => '学者']);
        $this->assertDatabaseHas('site_settings', ['key' => 'registration.require_admin_approval']);
    }
}
