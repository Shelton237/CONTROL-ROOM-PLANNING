<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_set_a_schedule_cell_override(): void
    {
        $manager = User::factory()->manager()->create();
        $room = Room::factory()->create();
        $employee = Employee::factory()->for($room)->rotation()->create();

        $response = $this->actingAs($manager, 'sanctum')->patchJson("/api/rooms/{$room->id}/schedule", [
            'week' => '2026-01-05',
            'employee_id' => $employee->id,
            'day_index' => 2,
            'value' => 'J',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('schedule_overrides', [
            'room_id' => $room->id,
            'week_start' => '2026-01-05',
            'employee_id' => $employee->id,
            'day_index' => 2,
            'value' => 'J',
        ]);

        // upsert : un second appel met à jour la même ligne (contrainte unique respectée)
        $this->actingAs($manager, 'sanctum')->patchJson("/api/rooms/{$room->id}/schedule", [
            'week' => '2026-01-05',
            'employee_id' => $employee->id,
            'day_index' => 2,
            'value' => 'N',
        ])->assertOk();

        $this->assertDatabaseCount('schedule_overrides', 1);
        $this->assertDatabaseHas('schedule_overrides', ['value' => 'N']);
    }

    public function test_reset_week_deletes_all_overrides_for_that_room_and_week(): void
    {
        $manager = User::factory()->manager()->create();
        $room = Room::factory()->create();
        $employee = Employee::factory()->for($room)->rotation()->create();

        \App\Models\ScheduleOverride::create([
            'room_id' => $room->id, 'week_start' => '2026-01-05',
            'employee_id' => $employee->id, 'day_index' => 0, 'value' => 'J',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/rooms/{$room->id}/schedule/reset", ['week' => '2026-01-05'])
            ->assertOk();

        $this->assertDatabaseCount('schedule_overrides', 0);
    }

    public function test_diffusion_preview_generates_expected_email_body_format(): void
    {
        $manager = User::factory()->manager()->create();
        $room = Room::factory()->create(['name' => 'Ankorodrano']);
        Employee::factory()->for($room)->fixedDay()->create(['name' => 'Sylvie Rabe', 'email' => 'sylvie@thara-services.mg']);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/rooms/{$room->id}/diffusion?week=2026-01-05");

        $response->assertOk();
        $body = $response->json('0.body');
        $this->assertStringContainsString('Bonjour Sylvie,', $body);
        $this->assertStringContainsString('Control Room Ankorodrano', $body);
        $this->assertStringContainsString('Jour = 07h30–17h30', $body);
        $this->assertStringContainsString('Toute demande de permission doit être soumise au moins 48 h à l\'avance.', $body);
    }
}
