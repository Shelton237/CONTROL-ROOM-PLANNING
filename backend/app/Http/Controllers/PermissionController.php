<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Support\AbsenceService;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function __construct(private AbsenceService $absenceService)
    {
    }

    /**
     * Manager : saisit une demande de permission pour un employé donné.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        return $this->createPermission($employee, $data);
    }

    /**
     * Agent : soumet sa propre demande de permission (scope = employee_id du user connecté).
     */
    public function storeMine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 404, 'Aucun employé associé à ce compte.');

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string'],
        ]);

        return $this->createPermission($employee, $data);
    }

    private function createPermission(Employee $employee, array $data)
    {
        $end = $data['end_date'] ?? $data['start_date'];

        $absence = $this->absenceService->submitPermission(
            $employee,
            $data['start_date'],
            $end,
            $data['reason'] ?? null
        );

        return response()->json($absence, 201);
    }
}
