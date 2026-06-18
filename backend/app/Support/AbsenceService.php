<?php

namespace App\Support;

use App\Models\Absence;
use App\Models\Employee;
use Illuminate\Support\Carbon;

/**
 * Règle des 48h pour les permissions, port fidèle de hoursUntil()/submitPermission()
 * du prototype : si l'écart entre maintenant et le début de la permission est
 * inférieur à 48h, la demande est refusée (et n'impacte pas le planning),
 * sinon elle est enregistrée (équivaut à une absence pour le planning).
 */
class AbsenceService
{
    public function hoursUntil(string $startDateIso): float
    {
        $start = Carbon::createFromFormat('Y-m-d', $startDateIso)->startOfDay();

        return Carbon::now()->diffInMinutes($start, false) / 60;
    }

    /**
     * Crée une absence déclarée par le manager : toujours "enregistree", effective immédiatement.
     */
    public function declareAbsence(Employee $employee, string $start, string $end, ?string $reason): Absence
    {
        return $employee->absences()->create([
            'start_date' => $start,
            'end_date' => $end,
            'type' => 'absence',
            'reason' => $reason,
            'status' => 'enregistree',
        ]);
    }

    /**
     * Soumet une demande de permission (par l'agent ou saisie par le manager) et
     * applique la règle des 48h.
     */
    public function submitPermission(Employee $employee, string $start, string $end, ?string $reason): Absence
    {
        $hours = $this->hoursUntil($start);
        $status = $hours < 48 ? 'refusee' : 'enregistree';

        return $employee->absences()->create([
            'start_date' => $start,
            'end_date' => $end,
            'type' => 'permission',
            'reason' => $reason,
            'status' => $status,
        ]);
    }
}
