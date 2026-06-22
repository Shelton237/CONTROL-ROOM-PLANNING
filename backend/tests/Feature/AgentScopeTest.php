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
        // un agent jour fixe créé en premier ne doit jamais "voler" la place de l'agent
        // dans le roster renvoyé par /me/schedule (régression : roster[0] côté frontend).
        Employee::factory()->for($room)->fixedDay()->create();
        $employee = Employee::factory()->for($room)->rotation()->create();
        $user = User::factory()->agent($employee)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me/schedule?week=2026-01-05');

        $response->assertOk();
        $roster = $response->json('roster');
        $this->assertCount(1, $roster, 'le roster /me/schedule ne doit contenir que l\'employé connecté');
        $this->assertSame($employee->id, $roster[0]['id']);
        $this->assertArrayHasKey((string) $employee->id, $response->json('grid'));
        $this->assertCount(7, $response->json('rooms'));
        $this->assertSame($room->id, $response->json('rooms.0.id'));
    }

    public function test_agent_schedule_reflects_inter_room_loan_for_the_days_assigned(): void
    {
        $homeRoom = Room::factory()->create(['name' => 'Salle Origine']);
        $targetRoom = Room::factory()->create(['name' => 'Salle Renfort']);
        $employee = Employee::factory()->for($homeRoom)->rotation()->create();
        $user = User::factory()->agent($employee)->create();

        \App\Models\RoomWeekLoan::create([
            'room_id' => $targetRoom->id,
            'week_start' => '2026-01-05',
            'employee_id' => $employee->id,
        ]);

        // le manager assigne explicitement le lundi (index 0) dans la salle de renfort
        \App\Models\ScheduleOverride::create([
            'room_id' => $targetRoom->id,
            'week_start' => '2026-01-05',
            'employee_id' => $employee->id,
            'day_index' => 0,
            'value' => 'J',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me/schedule?week=2026-01-05');

        $response->assertOk();
        $this->assertSame('J', $response->json("grid.{$employee->id}.0"));
        $this->assertSame($targetRoom->id, $response->json('rooms.0.id'));
        $this->assertSame('Salle Renfort', $response->json('rooms.0.name'));

        // les autres jours (non assignés dans la salle de renfort) retombent sur la salle d'origine
        $this->assertSame($homeRoom->id, $response->json('rooms.1.id'));
        $this->assertSame('Salle Origine', $response->json('rooms.1.name'));
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
        // soumise par l'agent, plus de 48h à l'avance -> en_attente (validation manager requise)
        $this->assertDatabaseHas('absences', [
            'employee_id' => $employee->id,
            'status' => 'en_attente',
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
