<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Room;
use App\Models\ScheduleOverride;
use App\Support\AbsenceService;
use App\Support\ScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_status_is_used_when_no_override_and_no_absence(): void
    {
        $room = Room::factory()->create();
        $employee = Employee::factory()->for($room)->rotation(0)->create();

        $service = new ScheduleService();
        $result = $service->weekSchedule($room, '2026-01-05'); // lundi, jour 0 du cycle (REF)

        // offset 0 sur 2026-01-05 (lundi, jour 4 depuis REF=2026-01-01) -> (4+0)%3=1 -> N
        $this->assertSame('N', $result['grid'][$employee->id][0]);
    }

    public function test_manual_override_takes_priority_over_auto_status(): void
    {
        $room = Room::factory()->create();
        $employee = Employee::factory()->for($room)->rotation(0)->create();

        ScheduleOverride::create([
            'room_id' => $room->id,
            'week_start' => '2026-01-05',
            'employee_id' => $employee->id,
            'day_index' => 0,
            'value' => 'J',
        ]);

        $service = new ScheduleService();
        $result = $service->weekSchedule($room, '2026-01-05');

        $this->assertSame('J', $result['grid'][$employee->id][0]);
    }

    public function test_absence_takes_priority_over_override_and_auto_status(): void
    {
        $room = Room::factory()->create();
        $employee = Employee::factory()->for($room)->rotation(0)->create();

        // override manuel sur le lundi
        ScheduleOverride::create([
            'room_id' => $room->id,
            'week_start' => '2026-01-05',
            'employee_id' => $employee->id,
            'day_index' => 0,
            'value' => 'J',
        ]);

        // absence enregistrée qui couvre ce jour -> doit l'emporter sur l'override
        $absenceService = new AbsenceService();
        $absenceService->declareAbsence($employee, '2026-01-05', '2026-01-05', 'maladie');

        $service = new ScheduleService();
        $result = $service->weekSchedule($room, '2026-01-05');

        $this->assertSame('ABS', $result['grid'][$employee->id][0]);
    }

    public function test_coverage_flags_low_staffing(): void
    {
        $room = Room::factory()->create();
        // un seul agent en J ce jour -> couverture < 2 -> alerte attendue côté frontend
        $employee = Employee::factory()->for($room)->fixedDay()->create();

        $service = new ScheduleService();
        $result = $service->weekSchedule($room, '2026-01-05');

        $this->assertSame(1, $result['coverage']['J'][0]); // lundi : 1 seul agent jour fixe
        $this->assertLessThan(2, $result['coverage']['J'][0]);
    }

    public function test_loaned_employee_has_no_auto_cycle_and_appears_as_cross(): void
    {
        $roomA = Room::factory()->create();
        $roomB = Room::factory()->create();
        $loanedEmployee = Employee::factory()->for($roomA)->rotation(0)->create();

        \App\Models\RoomWeekLoan::create([
            'room_id' => $roomB->id,
            'week_start' => '2026-01-05',
            'employee_id' => $loanedEmployee->id,
        ]);

        $service = new ScheduleService();
        $result = $service->weekSchedule($roomB, '2026-01-05');

        $roster = $result['roster'];
        $cross = $roster->firstWhere('id', $loanedEmployee->id);
        $this->assertNotNull($cross);
        $this->assertTrue($cross->cross);
        // pas de cycle propre : '' tant que le manager n'a rien assigné
        $this->assertSame('', $result['grid'][$loanedEmployee->id][0]);
    }

    public function test_reassign_offsets_groups_rotation_employees_by_pairs_in_creation_order(): void
    {
        $room = Room::factory()->create();
        $employees = Employee::factory()->for($room)->rotation(0)->count(6)->create();

        $service = new ScheduleService();
        $service->reassignOffsets($room);

        $sorted = $room->employees()->where('type', 'rotation')->orderBy('id')->get();
        $expectedOffsets = [0, 0, 1, 1, 2, 2];
        $expectedBinomes = [1, 1, 2, 2, 3, 3];

        $this->assertSame($expectedOffsets, $sorted->pluck('offset')->all());
        $this->assertSame($expectedBinomes, $sorted->pluck('binome')->all());
    }
}
