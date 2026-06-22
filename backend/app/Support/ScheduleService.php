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

        $control = $roster->first(fn ($e) => $e->type === 'fixed_day' && ! ($e->cross ?? false));
        $rotating = $roster->filter(fn ($e) => $e->type === 'rotation' && ! ($e->cross ?? false));

        $grid = [];
        foreach ($roster as $employee) {
            $grid[$employee->id] = [];
        }

        // jours où un employé de cette salle est en réalité prêté ET assigné ailleurs ce
        // jour précis : il doit disparaître du planning (et de la couverture) de sa salle
        // d'origine ce jour-là, pour ne pas afficher une couverture qui n'est pas réelle.
        $awayDaysByEmployee = [];
        foreach ($roster as $employee) {
            $awayDaysByEmployee[$employee->id] = ($employee->cross ?? false)
                ? []
                : $this->awayDaysFor($employee, $room, $monIso);
        }

        foreach ($dates as $d => $iso) {
            // un agent rotation absent (congé/maladie ou prêté ailleurs ce jour-là) ne doit
            // pas faire perdre inutilement le J de son partenaire de binôme.
            $awayThisDay = $rotating
                ->filter(function ($employee) use ($d, $iso, $awayDaysByEmployee, $absencesByEmployee) {
                    if (in_array($d, $awayDaysByEmployee[$employee->id], true)) {
                        return true;
                    }

                    return $absencesByEmployee->get($employee->id, collect())
                        ->contains(fn ($a) => $a->coversDate($iso));
                })
                ->pluck('id')
                ->all();

            $autoForDay = PlanningEngine::autoStatusesForRotation($rotating, $control, $iso, $monIso, $awayThisDay);

            foreach ($roster as $employee) {
                $employeeOverrides = $overrides->get($employee->id, collect())->keyBy('day_index');
                $employeeAbsences = $absencesByEmployee->get($employee->id, collect());
                $isAway = in_array($d, $awayDaysByEmployee[$employee->id], true);

                $grid[$employee->id][$d] = $this->effectiveCell(
                    $employee,
                    $iso,
                    $monIso,
                    $d,
                    $employeeOverrides,
                    $employeeAbsences,
                    $autoForDay[$employee->id] ?? null,
                    $isAway
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
     * Valeur effective d'une cellule : ABS > prêté ailleurs ce jour-là (absent de cette
     * salle) > override manuel > statut auto (ou '' si prêté sans override).
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
        Collection $absences,
        ?string $precomputedAuto = null,
        bool $isAway = false
    ): string {
        $isAbsent = $absences->contains(fn ($a) => $a->coversDate($iso));
        if ($isAbsent) {
            return 'ABS';
        }

        if ($isAway) {
            return '';
        }

        // Un override existe dès qu'une ligne schedule_overrides existe pour ce jour, même
        // avec value="" (le manager a explicitement cliqué jusqu'à "vide" dans le cycle
        // J→N→R→vide) : ça doit afficher une case vraiment vide, pas retomber sur le calcul
        // automatique — sinon le cycle se bloque dès que la valeur auto du jour est "R".
        $override = $overridesByDay->get($dayIndex);
        if ($override !== null) {
            return $override->value ?? '';
        }

        if ($employee->cross ?? false) {
            // agent prêté : pas de cycle propre, seulement ce que le manager assigne
            return '';
        }

        if ($employee->type === 'fixed_day') {
            return PlanningEngine::autoStatus($employee, $iso, $monIso);
        }

        return $precomputedAuto ?? PlanningEngine::autoStatus($employee, $iso, $monIso);
    }

    /**
     * Jours (0-6) où un employé de cette salle est effectivement assigné dans une AUTRE
     * salle (prêt + valeur non vide assignée par le manager là-bas) — donc absent de sa
     * salle d'origine ces jours précis.
     *
     * @return int[]
     */
    private function awayDaysFor(Employee $employee, Room $homeRoom, string $monIso): array
    {
        $loanRoomIds = RoomWeekLoan::query()
            ->where('employee_id', $employee->id)
            ->where('week_start', $monIso)
            ->where('room_id', '!=', $homeRoom->id)
            ->pluck('room_id');

        if ($loanRoomIds->isEmpty()) {
            return [];
        }

        return ScheduleOverride::query()
            ->whereIn('room_id', $loanRoomIds)
            ->where('week_start', $monIso)
            ->where('employee_id', $employee->id)
            ->where('value', '!=', '')
            ->pluck('day_index')
            ->all();
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
     * Planning personnel d'un employé pour une semaine : pour chaque jour, la valeur
     * effective ET la salle d'où elle provient. Un agent peut être prêté à une (ou
     * plusieurs, selon les jours) autre salle que la sienne au cours de la même semaine :
     * pour chaque jour, on utilise la salle de prêt si elle a une valeur assignée par le
     * manager ce jour-là (non vide), sinon on retombe sur la salle d'origine.
     *
     * @return array{dates: string[], grid: array<int, string>, rooms: array<int, array{id:int,name:string}>}
     */
    public function meWeekSchedule(Employee $employee, string $weekStart): array
    {
        $monIso = PlanningEngine::mondayOf($weekStart);
        $dates = PlanningEngine::weekDates($monIso);

        $homeResult = $this->weekSchedule($employee->room, $monIso);
        $homeValues = $homeResult['grid'][$employee->id] ?? array_fill(0, 7, '');

        $loanRoomIds = RoomWeekLoan::query()
            ->where('employee_id', $employee->id)
            ->where('week_start', $monIso)
            ->where('room_id', '!=', $employee->room_id)
            ->pluck('room_id');

        $loanResults = Room::query()
            ->whereIn('id', $loanRoomIds)
            ->get()
            ->map(fn ($room) => ['room' => $room, 'result' => $this->weekSchedule($room, $monIso)]);

        $grid = [];
        $rooms = [];

        foreach ($dates as $d => $iso) {
            $value = $homeValues[$d] ?? '';
            $roomInfo = ['id' => $employee->room_id, 'name' => $employee->room->name];

            foreach ($loanResults as $loan) {
                $loanValue = $loan['result']['grid'][$employee->id][$d] ?? '';
                if ($loanValue !== '') {
                    $value = $loanValue;
                    $roomInfo = ['id' => $loan['room']->id, 'name' => $loan['room']->name];
                    break;
                }
            }

            $grid[$d] = $value;
            $rooms[$d] = $roomInfo;
        }

        return [
            'dates' => $dates,
            'grid' => $grid,
            'rooms' => $rooms,
        ];
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
