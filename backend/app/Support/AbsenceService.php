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
     * Demande de permission soumise par le manager directement (pour un employé) :
     * applique la règle des 48h et décide immédiatement (le manager est déjà le
     * validateur, pas besoin d'une étape d'approbation supplémentaire).
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

    /**
     * Demande de permission soumise par l'agent lui-même : la règle des 48h ne fait que
     * refuser d'office les demandes trop tardives (statut définitif, le délai est
     * intangible) ; sinon la demande reste `en_attente` jusqu'à ce que le manager la
     * valide ou la rejette explicitement.
     */
    public function requestPermission(Employee $employee, string $start, string $end, ?string $reason): Absence
    {
        $hours = $this->hoursUntil($start);
        $status = $hours < 48 ? 'refusee' : 'en_attente';

        return $employee->absences()->create([
            'start_date' => $start,
            'end_date' => $end,
            'type' => 'permission',
            'reason' => $reason,
            'status' => $status,
        ]);
    }

    /**
     * Le manager valide une demande en attente : elle devient enregistree (équivaut à
     * une absence pour le planning).
     */
    public function approve(Absence $absence): Absence
    {
        $absence->update(['status' => 'enregistree']);

        return $absence;
    }

    /**
     * Le manager rejette une demande en attente : elle n'impacte pas le planning, mais
     * reste visible dans l'historique.
     */
    public function reject(Absence $absence): Absence
    {
        $absence->update(['status' => 'refusee']);

        return $absence;
    }
}
