<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomWeekLoan;
use App\Models\ScheduleOverride;
use App\Support\PlanningEngine;
use App\Support\ScheduleService;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(private ScheduleService $scheduleService)
    {
    }

    public function show(Request $request, Room $room)
    {
        $week = $request->query('week') ?: PlanningEngine::mondayOf(now()->format('Y-m-d'));

        return response()->json($this->buildPayload($room, $week));
    }

    /**
     * Même forme que show() mais limité à l'employé courant (scope agent).
     */
    public function showMine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 404, 'Aucun employé associé à ce compte.');

        $week = $request->query('week') ?: PlanningEngine::mondayOf(now()->format('Y-m-d'));
        $payload = $this->buildPayload($employee->room, $week);

        return response()->json($payload);
    }

    public function update(Request $request, Room $room)
    {
        $data = $request->validate([
            'week' => ['required', 'date'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'day_index' => ['required', 'integer', 'min:0', 'max:6'],
            'value' => ['present', 'string', 'in:J,N,R,'],
        ]);

        $weekStart = PlanningEngine::mondayOf($data['week']);

        $override = ScheduleOverride::updateOrCreate(
            [
                'room_id' => $room->id,
                'week_start' => $weekStart,
                'employee_id' => $data['employee_id'],
                'day_index' => $data['day_index'],
            ],
            ['value' => $data['value']]
        );

        return response()->json($override);
    }

    public function reset(Request $request, Room $room)
    {
        $data = $request->validate(['week' => ['required', 'date']]);
        $weekStart = PlanningEngine::mondayOf($data['week']);

        ScheduleOverride::where('room_id', $room->id)->where('week_start', $weekStart)->delete();

        return response()->json(['message' => 'ok']);
    }

    public function addLoan(Request $request, Room $room)
    {
        $data = $request->validate([
            'week' => ['required', 'date'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ]);
        $weekStart = PlanningEngine::mondayOf($data['week']);

        $loan = RoomWeekLoan::firstOrCreate([
            'room_id' => $room->id,
            'week_start' => $weekStart,
            'employee_id' => $data['employee_id'],
        ]);

        return response()->json($loan, 201);
    }

    public function removeLoan(Request $request, Room $room)
    {
        $data = $request->validate([
            'week' => ['required', 'date'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ]);
        $weekStart = PlanningEngine::mondayOf($data['week']);

        RoomWeekLoan::where('room_id', $room->id)
            ->where('week_start', $weekStart)
            ->where('employee_id', $data['employee_id'])
            ->delete();

        ScheduleOverride::where('room_id', $room->id)
            ->where('week_start', $weekStart)
            ->where('employee_id', $data['employee_id'])
            ->delete();

        return response()->json(['message' => 'ok']);
    }

    private function buildPayload(Room $room, string $week): array
    {
        $result = $this->scheduleService->weekSchedule($room, $week);

        $roster = $result['roster']->map(function ($e) {
            return [
                'id' => $e->id,
                'room_id' => $e->room_id,
                'name' => $e->name,
                'email' => $e->email,
                'type' => $e->type,
                'offset' => $e->offset,
                'binome' => $e->binome,
                'day_spec' => $e->day_spec,
                'alt_parity' => $e->alt_parity,
                'cross' => $e->cross ?? false,
            ];
        })->values();

        return [
            'dates' => $result['dates'],
            'roster' => $roster,
            'grid' => $result['grid'],
            'coverage' => $result['coverage'],
        ];
    }
}
