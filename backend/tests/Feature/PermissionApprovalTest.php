<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PermissionApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_submitted_permission_is_en_attente_until_manager_decides(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $employee = Employee::factory()->rotation()->create();
        $agent = User::factory()->agent($employee)->create();

        $response = $this->actingAs($agent, 'sanctum')->postJson('/api/me/permissions', [
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-01',
            'reason' => 'voyage',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('status', 'en_attente');

        Carbon::setTestNow();
    }

    public function test_manager_can_approve_a_pending_permission(): void
    {
        $employee = Employee::factory()->rotation()->create();
        $manager = User::factory()->manager()->create();
        $absence = \App\Models\Absence::create([
            'employee_id' => $employee->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-01',
            'type' => 'permission',
            'reason' => 'voyage',
            'status' => 'en_attente',
        ]);

        $response = $this->actingAs($manager, 'sanctum')->postJson("/api/absences/{$absence->id}/approve");

        $response->assertOk();
        $response->assertJsonPath('status', 'enregistree');
        $this->assertDatabaseHas('absences', ['id' => $absence->id, 'status' => 'enregistree']);
    }

    public function test_manager_can_reject_a_pending_permission(): void
    {
        $employee = Employee::factory()->rotation()->create();
        $manager = User::factory()->manager()->create();
        $absence = \App\Models\Absence::create([
            'employee_id' => $employee->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-01',
            'type' => 'permission',
            'reason' => 'voyage',
            'status' => 'en_attente',
        ]);

        $response = $this->actingAs($manager, 'sanctum')->postJson("/api/absences/{$absence->id}/reject");

        $response->assertOk();
        $response->assertJsonPath('status', 'refusee');
        $this->assertDatabaseHas('absences', ['id' => $absence->id, 'status' => 'refusee']);
    }

    public function test_approve_fails_if_request_is_no_longer_pending(): void
    {
        $employee = Employee::factory()->rotation()->create();
        $manager = User::factory()->manager()->create();
        $absence = \App\Models\Absence::create([
            'employee_id' => $employee->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-01',
            'type' => 'permission',
            'reason' => 'voyage',
            'status' => 'refusee',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/absences/{$absence->id}/approve")
            ->assertStatus(422);
    }

    public function test_agent_cannot_approve_or_reject_permissions(): void
    {
        $employee = Employee::factory()->rotation()->create();
        $agent = User::factory()->agent($employee)->create();
        $absence = \App\Models\Absence::create([
            'employee_id' => $employee->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-01',
            'type' => 'permission',
            'reason' => 'voyage',
            'status' => 'en_attente',
        ]);

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/absences/{$absence->id}/approve")
            ->assertForbidden();
    }
}
