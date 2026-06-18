<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Support\ScheduleService;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(private ScheduleService $scheduleService)
    {
    }

    public function index(Request $request)
    {
        $query = Employee::query();

        if ($request->filled('room_id')) {
            $query->where('room_id', $request->integer('room_id'));
        }

        return $query->orderBy('id')->get();
    }

    public function store(Request $request)
    {
        $data = $this->validateEmployee($request);

        $employee = Employee::create($data);

        if ($employee->type === 'rotation') {
            $this->scheduleService->reassignOffsets($employee->room);
            $employee->refresh();
        }

        return response()->json($employee, 201);
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validateEmployee($request, $employee);

        $employee->update($data);

        $this->scheduleService->reassignOffsets($employee->room);
        $employee->refresh();

        return response()->json($employee);
    }

    public function destroy(Employee $employee)
    {
        $room = $employee->room;
        $employee->delete();
        $this->scheduleService->reassignOffsets($room);

        return response()->json(['message' => 'ok']);
    }

    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        $rules = [
            'room_id' => [$employee ? 'sometimes' : 'required', 'integer', 'exists:rooms,id'],
            'name' => [$employee ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'type' => [$employee ? 'sometimes' : 'required', 'in:rotation,fixed_day'],
            'day_spec' => ['nullable', 'array', 'size:7'],
            'day_spec.*' => ['in:on,off,alt'],
            'alt_parity' => ['nullable', 'integer', 'in:0,1'],
        ];

        $data = $request->validate($rules);

        $type = $data['type'] ?? $employee?->type;
        if ($type === 'fixed_day' && ! array_key_exists('day_spec', $data) && ! $employee) {
            $data['day_spec'] = \App\Support\PlanningEngine::defaultSpec();
        }
        if ($type === 'fixed_day' && ! array_key_exists('alt_parity', $data) && ! $employee) {
            $data['alt_parity'] = 0;
        }
        if ($type === 'rotation') {
            $data['day_spec'] = null;
            $data['alt_parity'] = null;
        }

        return $data;
    }
}
