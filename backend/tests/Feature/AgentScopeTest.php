<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgentScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_schedule_is_scoped_to_own_employee_room(): void
    {
        $room = Room::factory()->create();
        $employee = Employee::factory()->for($room)->rotation()->create();
        $user = User::factory()->agent($employee)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me/schedule?week=2026-01-05');

        $response->assertOk();
        $ids = collect($response->json('roster'))->pluck('id')->all();
        $this->assertContains($employee->id, $ids);
    }

    public function test_agent_permission_is_always_created_for_own_employee_regardless_of_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $room = Room::factory()->create();
        $employee = Employee::factory()->for($room)->rotation()->create();
        $otherEmployee = Employee::factory()->for($room)->rotation(1)->create();
        $user = User::factory()->agent($employee)->create();

        // l'agent ne fournit pas d'employee_id (l'endpoint n'accepte pas ce champ) :
        // la permission doit être rattachée à son propre employee_id, jamais à un autre.
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/me/permissions', [
            'start_date' => '2026-01-10',
            'end_date' => '2026-01-10',
            'reason' => 'rdv',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('absences', [
            'employee_id' => $employee->id,
            'status' => 'enregistree',
        ]);
        $this->assertDatabaseMissing('absences', [
            'employee_id' => $otherEmployee->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_agent_absences_endpoint_only_returns_own_absences(): void
    {
        $room = Room::factory()->create();
        $employee = Employee::factory()->for($room)->rotation()->create();
        $otherEmployee = Employee::factory()->for($room)->rotation(1)->create();
        $user = User::factory()->agent($employee)->create();

        $employee->absences()->create([
            'start_date' => '2026-01-02', 'end_date' => '2026-01-02',
            'type' => 'absence', 'status' => 'enregistree',
        ]);
        $otherEmployee->absences()->create([
            'start_date' => '2026-01-03', 'end_date' => '2026-01-03',
            'type' => 'absence', 'status' => 'enregistree',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me/absences');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame($employee->id, $response->json('0.employee_id'));
    }
}
