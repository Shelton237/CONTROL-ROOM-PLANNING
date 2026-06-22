<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Room;
use App\Support\PlanningEngine;
use PHPUnit\Framework\TestCase;

class PlanningEngineTest extends TestCase
{
    /**
     * REF = 2026-01-01 est le jour 0 du cycle. Avec offset 0, le cycle est J,N,R,J,N,R...
     */
    public function test_cycle_is_anchored_on_ref_date_for_offset_zero(): void
    {
        $employee = new Employee(['type' => 'rotation', 'offset' => 0]);

        $this->assertSame('J', PlanningEngine::autoStatus($employee, '2026-01-01', '2025-12-29'));
        $this->assertSame('N', PlanningEngine::autoStatus($employee, '2026-01-02', '2025-12-29'));
        $this->assertSame('R', PlanningEngine::autoStatus($employee, '2026-01-03', '2025-12-29'));
        $this->assertSame('J', PlanningEngine::autoStatus($employee, '2026-01-04', '2025-12-29'));
    }

    public function test_cycle_shifts_with_offset(): void
    {
        // offset=1 -> binome 2 : décalé d'un jour dans le cycle J,N,R
        $employee = new Employee(['type' => 'rotation', 'offset' => 1]);

        $this->assertSame('N', PlanningEngine::autoStatus($employee, '2026-01-01', '2025-12-29'));
        $this->assertSame('R', PlanningEngine::autoStatus($employee, '2026-01-02', '2025-12-29'));
        $this->assertSame('J', PlanningEngine::autoStatus($employee, '2026-01-03', '2025-12-29'));
    }

    public function test_cycle_works_for_dates_before_ref(): void
    {
        $employee = new Employee(['type' => 'rotation', 'offset' => 0]);

        // 2025-12-31 = REF - 1 jour -> dayNum = -1 -> ((-1 % 3)+3)%3 = 2 -> R
        $this->assertSame('R', PlanningEngine::autoStatus($employee, '2025-12-31', '2025-12-29'));
        // 2025-12-30 = REF - 2 -> dayNum=-2 -> ((-2%3)+3)%3=1 -> N
        $this->assertSame('N', PlanningEngine::autoStatus($employee, '2025-12-30', '2025-12-29'));
    }

    public function test_fixed_day_employee_works_on_spec_days(): void
    {
        // Lundi à samedi = on, dimanche = off (defaultSpec)
        $employee = new Employee([
            'type' => 'fixed_day',
            'day_spec' => PlanningEngine::defaultSpec(),
            'alt_parity' => 0,
        ]);

        $monIso = '2026-01-05'; // lundi
        $this->assertSame('J', PlanningEngine::autoStatus($employee, '2026-01-05', $monIso)); // lundi
        $this->assertSame('J', PlanningEngine::autoStatus($employee, '2026-01-10', $monIso)); // samedi
        $this->assertSame('R', PlanningEngine::autoStatus($employee, '2026-01-11', $monIso)); // dimanche
    }

    public function test_fixed_day_alt_parity_depends_on_iso_week_number(): void
    {
        // samedi (index 5) en 'alt', actif les semaines paires (alt_parity=0)
        $spec = ['on', 'on', 'on', 'on', 'on', 'alt', 'off'];
        $employee = new Employee([
            'type' => 'fixed_day',
            'day_spec' => $spec,
            'alt_parity' => 0,
        ]);

        // 2026-01-05 (lundi) est en semaine ISO 2 (paire) -> alt actif
        $this->assertSame(0, PlanningEngine::isoWeekNum('2026-01-05') % 2);
        $this->assertSame('J', PlanningEngine::autoStatus($employee, '2026-01-10', '2026-01-05')); // samedi sem paire

        // semaine suivante = impaire -> alt inactif -> repos le samedi
        $nextMon = '2026-01-12';
        $this->assertSame(1, PlanningEngine::isoWeekNum($nextMon) % 2);
        $this->assertSame('R', PlanningEngine::autoStatus($employee, '2026-01-17', $nextMon)); // samedi sem impaire
    }

    public function test_offset_and_binome_assignment_by_rank(): void
    {
        // 0,0,1,1,2,2,...
        $this->assertSame(0, PlanningEngine::offsetForRank(0));
        $this->assertSame(0, PlanningEngine::offsetForRank(1));
        $this->assertSame(1, PlanningEngine::offsetForRank(2));
        $this->assertSame(1, PlanningEngine::offsetForRank(3));
        $this->assertSame(2, PlanningEngine::offsetForRank(4));
        $this->assertSame(2, PlanningEngine::offsetForRank(5));

        $this->assertSame(1, PlanningEngine::binomeForRank(0));
        $this->assertSame(2, PlanningEngine::binomeForRank(2));
        $this->assertSame(3, PlanningEngine::binomeForRank(4));
    }

    public function test_coverage_counts_j_and_n_per_day(): void
    {
        $grid = [
            1 => ['J', 'N', 'R', 'R', 'R', 'R', 'R'],
            2 => ['J', 'N', 'R', 'R', 'R', 'R', 'R'],
            3 => ['R', 'J', 'R', 'R', 'R', 'R', 'R'],
        ];

        $coverage = PlanningEngine::coverage($grid, [1, 2, 3]);

        $this->assertSame([2, 1, 0, 0, 0, 0, 0], $coverage['J']);
        $this->assertSame([0, 2, 0, 0, 0, 0, 0], $coverage['N']);
    }

    public function test_three_binomes_plus_control_rotate_daily_with_exactly_two_on_j_and_n(): void
    {
        $control = new Employee([
            'type' => 'fixed_day',
            'day_spec' => PlanningEngine::defaultSpec(), // lun-sam 'on', dim 'off'
            'alt_parity' => 0,
        ]);

        $employees = collect([
            (new Employee(['type' => 'rotation', 'offset' => 0, 'binome' => 1]))->forceFill(['id' => 1]),
            (new Employee(['type' => 'rotation', 'offset' => 0, 'binome' => 1]))->forceFill(['id' => 2]),
            (new Employee(['type' => 'rotation', 'offset' => 1, 'binome' => 2]))->forceFill(['id' => 3]),
            (new Employee(['type' => 'rotation', 'offset' => 1, 'binome' => 2]))->forceFill(['id' => 4]),
            (new Employee(['type' => 'rotation', 'offset' => 2, 'binome' => 3]))->forceFill(['id' => 5]),
            (new Employee(['type' => 'rotation', 'offset' => 2, 'binome' => 3]))->forceFill(['id' => 6]),
        ]);

        $previous = [];

        // 9 semaines consécutives (3 cycles complets de 3 semaines) pour observer toutes
        // les transitions, y compris d'une semaine à l'autre.
        for ($d = 0; $d < 63; $d++) {
            $iso = \Carbon\CarbonImmutable::createFromFormat('Y-m-d', '2026-01-05', 'UTC')->addDays($d)->format('Y-m-d');
            $monIso = PlanningEngine::mondayOf($iso);

            $statuses = PlanningEngine::autoStatusesForRotation($employees, $control, $iso, $monIso);
            $controlStatus = PlanningEngine::autoStatus($control, $iso, $monIso);
            $all = array_merge(array_values($statuses), [$controlStatus]);

            $jCount = count(array_filter($all, fn ($s) => $s === 'J'));
            $nCount = count(array_filter($all, fn ($s) => $s === 'N'));

            // Avec 3 binômes, une phase couvre toujours exactement 1 binôme par jour :
            // J et N restent à exactement 2 tous les jours, contrôle compris, sans exception.
            $this->assertSame(2, $jCount, "J != 2 le $iso");
            $this->assertSame(2, $nCount, "N != 2 le $iso");

            // Aucun agent ne doit jamais passer directement de N (hier) à J (aujourd'hui).
            foreach ($statuses as $employeeId => $status) {
                if (isset($previous[$employeeId])) {
                    $this->assertFalse(
                        $previous[$employeeId] === 'N' && $status === 'J',
                        "agent {$employeeId} passe de N à J directement le {$iso}"
                    );
                }
                $previous[$employeeId] = $status;
            }

            // Les deux membres d'un même binôme ne sont jamais sur des statuts différents
            // SAUF le binôme "scindé" sur sa semaine J (l'un en J, l'autre en R).
            foreach ([[1, 2], [3, 4], [5, 6]] as [$idA, $idB]) {
                $pair = [$statuses[$idA], $statuses[$idB]];
                $this->assertTrue(
                    $pair[0] === $pair[1] || (in_array('J', $pair, true) && in_array('R', $pair, true)),
                    "binôme incohérent le $iso : ".implode(',', $pair)
                );
            }
        }
    }

    public function test_monday_of_returns_iso_monday_for_any_day_of_week(): void
    {
        $this->assertSame('2026-01-05', PlanningEngine::mondayOf('2026-01-05')); // lundi
        $this->assertSame('2026-01-05', PlanningEngine::mondayOf('2026-01-11')); // dimanche
        $this->assertSame('2026-01-05', PlanningEngine::mondayOf('2026-01-08')); // jeudi
    }
}
