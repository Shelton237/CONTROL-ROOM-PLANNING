<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Room;
use App\Models\RoomWeekLoan;
use App\Models\ScheduleOverride;
use Illuminate\Support\Collection;

/**
 * Construit le planning effectif d'une salle pour une semaine donnée :
 * roster (agents de la salle + agents prêtés), grid (cellule effective par jour),
 * et couverture J/N. Port fidèle de weekSchedule()/rosterFor() du prototype.
 */
class ScheduleService
{
    /**
     * @return array{dates: string[], roster: \Illuminate\Support\Collection, grid: array<int, array<int, string>>, coverage: array{J: int[], N: int[]}}
     */
    public function weekSchedule(Room $room, string $weekStart): array
    {
        $monIso = PlanningEngine::mondayOf($weekStart);
        $dates = PlanningEngine::weekDates($monIso);
        $roster = $this->rosterFor($room, $monIso);

        // overrides de la semaine, indexés par employee_id puis day_index
        $overrides = ScheduleOverride::query()
            ->where('room_id', $room->id)
            ->where('week_start', $monIso)
            ->get()
            ->groupBy('employee_id');

        // absences enregistrées des employés du roster
        $employeeIds = $roster->pluck('id')->all();
        $absencesByEmployee = \App\Models\Absence::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'enregistree')
            ->get()
            ->groupBy('employee_id');

        $grid = [];
        foreach ($roster as $employee) {
            $employeeOverrides = $overrides->get($employee->id, collect())->keyBy('day_index');
            $employeeAbsences = $absencesByEmployee->get($employee->id, collect());

            $grid[$employee->id] = [];
            foreach ($dates as $d => $iso) {
                $grid[$employee->id][$d] = $this->effectiveCell(
                    $employee,
                    $iso,
                    $monIso,
                    $d,
                    $employeeOverrides,
                    $employeeAbsences
                );
            }
        }

        $coverage = PlanningEngine::coverage($grid, $employeeIds);

        return [
            'dates' => $dates,
            'roster' => $roster,
            'grid' => $grid,
            'coverage' => $coverage,
        ];
    }

    /**
     * Valeur effective d'une cellule : ABS > override manuel > statut auto (ou '' si prêté sans override).
     *
     * @param  Collection  $overridesByDay  day_index => ScheduleOverride
     * @param  Collection  $absences  absences enregistrées de l'employé
     */
    public function effectiveCell(
        Employee $employee,
        string $iso,
        string $monIso,
        int $dayIndex,
        Collection $overridesByDay,
        Collection $absences
    ): string {
        $isAbsent = $absences->contains(fn ($a) => $a->coversDate($iso));
        if ($isAbsent) {
            return 'ABS';
        }

        $override = $overridesByDay->get($dayIndex);
        if ($override !== null && $override->value !== '' && $override->value !== null) {
            return $override->value;
        }

        if ($employee->cross ?? false) {
            // agent prêté : pas de cycle propre, seulement ce que le manager assigne
            return '';
        }

        return PlanningEngine::autoStatus($employee, $iso, $monIso);
    }

    /**
     * Roster = agents de la salle (triés) + agents prêtés présents dans les overrides de la semaine.
     *
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    public function rosterFor(Room $room, string $monIso): Collection
    {
        $base = $room->employees()->orderBy('id')->get();
        $base->each(fn ($e) => $e->cross = false);

        // tri : jour fixe d'abord, puis rotation par binôme
        $base = $base->sortBy([
            fn ($a, $b) => ($a->type === 'fixed_day' ? 0 : 1) <=> ($b->type === 'fixed_day' ? 0 : 1),
            fn ($a, $b) => ($a->binome ?? 0) <=> ($b->binome ?? 0),
        ])->values();

        $loanedIds = RoomWeekLoan::query()
            ->where('room_id', $room->id)
            ->where('week_start', $monIso)
            ->pluck('employee_id');

        $cross = Employee::query()
            ->whereIn('id', $loanedIds)
            ->get()
            ->each(fn ($e) => $e->cross = true);

        return $base->concat($cross)->values();
    }

    /**
     * Recalcule offset/binome de tous les agents rotation d'une salle, par ordre de création (id croissant).
     */
    public function reassignOffsets(Room $room): void
    {
        $rotations = $room->employees()->where('type', 'rotation')->orderBy('id')->get();
        foreach ($rotations as $rank => $employee) {
            $employee->offset = PlanningEngine::offsetForRank($rank);
            $employee->binome = PlanningEngine::binomeForRank($rank);
            $employee->save();
        }
    }
}
