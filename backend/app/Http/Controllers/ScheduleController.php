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
     * Planning de l'employé courant uniquement (scope agent) — jamais le roster complet de
     * la salle. Tient compte des prêts inter-salles : un agent peut être assigné à une autre
     * salle que la sienne certains jours de la semaine (champ `rooms`, par jour).
     */
    public function showMine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 404, 'Aucun employé associé à ce compte.');

        $week = $request->query('week') ?: PlanningEngine::mondayOf(now()->format('Y-m-d'));
        $monIso = PlanningEngine::mondayOf($week);

        $me = $this->scheduleService->meWeekSchedule($employee, $monIso);
        $grid = array_values($me['grid']);
        $coverage = PlanningEngine::coverage([$employee->id => $grid], [$employee->id]);

        return response()->json([
            'dates' => $me['dates'],
            'roster' => [[
                'id' => $employee->id,
                'room_id' => $employee->room_id,
                'name' => $employee->name,
                'email' => $employee->email,
                'type' => $employee->type,
                'offset' => $employee->offset,
                'binome' => $employee->binome,
                'day_spec' => $employee->day_spec,
                'alt_parity' => $employee->alt_parity,
                'cross' => false,
            ]],
            'grid' => [$employee->id => $grid],
            'coverage' => $coverage,
            'rooms' => $me['rooms'],
        ]);
    }

    public function update(Request $request, Room $room)
    {
        // Le middleware global de Laravel convertit toute chaîne vide reçue en `null`
        // avant la validation — la cellule "vide" du cycle (J→N→R→vide) arrive donc ici
        // en tant que `null`, jamais en tant que chaîne vide réelle. `nullable` doit donc
        // être présent en plus de `present`, sinon la règle `string` rejette ce `null`.
        $data = $request->validate([
            'week' => ['required', 'date'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'day_index' => ['required', 'integer', 'min:0', 'max:6'],
            'value' => ['present', 'nullable', 'in:J,N,R,'],
        ]);

        $weekStart = PlanningEngine::mondayOf($data['week']);

        ScheduleOverride::updateOrCreate(
            [
                'room_id' => $room->id,
                'week_start' => $weekStart,
                'employee_id' => $data['employee_id'],
                'day_index' => $data['day_index'],
            ],
            ['value' => $data['value'] ?? '']
        );

        return response()->json($this->buildPayload($room, $weekStart));
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
