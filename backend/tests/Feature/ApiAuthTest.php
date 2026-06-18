<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_and_user(): void
    {
        $user = User::factory()->manager()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role', 'employee_id']]);
    }

    public function test_login_fails_with_bad_credentials(): void
    {
        $user = User::factory()->manager()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ]);

        $response->assertStatus(422);
    }

    public function test_me_endpoint_returns_employee_for_agent(): void
    {
        $room = Room::factory()->create();
        $employee = Employee::factory()->for($room)->rotation()->create();
        $user = User::factory()->agent($employee)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me');

        $response->assertOk()->assertJson([
            'role' => 'agent',
            'employee_id' => $employee->id,
        ]);
        $response->assertJsonPath('employee.id', $employee->id);
    }

    public function test_manager_routes_are_forbidden_for_agent_role(): void
    {
        $room = Room::factory()->create();
        $employee = Employee::factory()->for($room)->rotation()->create();
        $user = User::factory()->agent($employee)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/rooms');

        $response->assertStatus(403);
    }

    public function test_agent_routes_are_forbidden_for_manager_role(): void
    {
        $user = User::factory()->manager()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me/schedule');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }
}
