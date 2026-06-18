<?php

namespace App\Http\Controllers;

use App\Models\Absence;
use App\Models\Employee;
use App\Support\AbsenceService;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    public function __construct(private AbsenceService $absenceService)
    {
    }

    public function index()
    {
        return Absence::query()
            ->with('employee')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $end = $data['end_date'] ?? $data['start_date'];

        $absence = $this->absenceService->declareAbsence(
            $employee,
            $data['start_date'],
            $end,
            $data['reason'] ?? null
        );

        return response()->json($absence, 201);
    }

    public function destroy(Absence $absence)
    {
        $absence->delete();

        return response()->json(['message' => 'ok']);
    }

    /**
     * Liste des absences/permissions de l'employé courant (scope agent).
     */
    public function mine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 404, 'Aucun employé associé à ce compte.');

        return Absence::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }
}
