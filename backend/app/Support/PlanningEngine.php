<?php

namespace App\Support;

use App\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * Port fidèle de la logique métier du prototype legacy (docs/legacy-prototype.html) :
 * cycle continu J-N-R ancré sur REF, jours fixes avec parité de semaine ISO,
 * couverture J/N, et génération du corps d'e-mail de diffusion.
 */
class PlanningEngine
{
    public const CYCLE = ['J', 'N', 'R'];

    public const REF = '2026-01-01';

    public const DAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

    public const DSHORT = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

    public const HOURS = [
        'J' => '07h30 – 17h30',
        'N' => '17h30 – 07h30 (lendemain)',
    ];

    /**
     * Lundi (0) .. Dimanche (6) pour une date ISO (yyyy-mm-dd), indépendant du fuseau (UTC).
     */
    public static function dowIso(string $iso): int
    {
        $d = CarbonImmutable::createFromFormat('Y-m-d', $iso, 'UTC');

        // Carbon: 0=Sunday..6=Saturday -> on veut 0=Lundi..6=Dimanche
        return ($d->dayOfWeekIso - 1 + 7) % 7;
    }

    public static function mondayOf(string $iso): string
    {
        $dow = self::dowIso($iso);

        return CarbonImmutable::createFromFormat('Y-m-d', $iso, 'UTC')->subDays($dow)->format('Y-m-d');
    }

    /**
     * Nombre de jours entiers écoulés depuis REF (peut être négatif).
     */
    public static function dayNum(string $iso): int
    {
        $ref = CarbonImmutable::createFromFormat('Y-m-d', self::REF, 'UTC');
        $d = CarbonImmutable::createFromFormat('Y-m-d', $iso, 'UTC');

        return $ref->diffInDays($d, false);
    }

    /**
     * Numéro de semaine ISO-8601 pour une date ISO.
     */
    public static function isoWeekNum(string $iso): int
    {
        return (int) CarbonImmutable::createFromFormat('Y-m-d', $iso, 'UTC')->isoWeek;
    }

    public static function weekParity(string $monIso): int
    {
        return self::isoWeekNum($monIso) % 2;
    }

    /**
     * @return string[] les 7 dates ISO de la semaine (lundi -> dimanche)
     */
    public static function weekDates(string $monIso): array
    {
        $d = CarbonImmutable::createFromFormat('Y-m-d', $monIso, 'UTC');

        return array_map(fn ($i) => $d->addDays($i)->format('Y-m-d'), range(0, 6));
    }

    public static function fmtShort(string $iso): string
    {
        $d = CarbonImmutable::createFromFormat('Y-m-d', $iso, 'UTC');
        $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

        return sprintf('%02d %s', $d->day, $months[$d->month - 1]);
    }

    /**
     * Jours travaillés (index 0..6) pour un employé fixed_day, pour une semaine donnée
     * (gère la parité 'alt').
     *
     * @return int[]
     */
    public static function workingDaysFor(Employee $employee, string $monIso): array
    {
        $spec = $employee->day_spec ?? self::defaultSpec();
        $parity = self::weekParity($monIso);
        $out = [];
        foreach ($spec as $d => $v) {
            if ($v === 'on') {
                $out[] = $d;
            } elseif ($v === 'alt' && $parity === ($employee->alt_parity ?? 0)) {
                $out[] = $d;
            }
        }

        return $out;
    }

    /**
     * @return string[] tableau de 7 valeurs 'on'|'off'
     */
    public static function defaultSpec(): array
    {
        $s = array_fill(0, 7, 'off');
        for ($i = 0; $i < 6; $i++) {
            $s[$i] = 'on';
        }

        return $s;
    }

    /**
     * Statut auto (avant absence/override) d'un employé pour une date donnée.
     */
    public static function autoStatus(Employee $employee, string $iso, string $monIso): string
    {
        if ($employee->type === 'rotation') {
            $n = (self::dayNum($iso) + ($employee->offset ?? 0)) % 3;
            $n = ($n + 3) % 3;

            return self::CYCLE[$n];
        }

        $workingDays = self::workingDaysFor($employee, $monIso);
        $dow = self::dowIso($iso);

        return in_array($dow, $workingDays, true) ? 'J' : 'R';
    }

    /**
     * Calcule l'offset/binôme assignés à un agent rotation selon son rang (0-based)
     * dans l'ordre de création au sein de sa room : 0,0,1,1,2,2,...
     */
    public static function offsetForRank(int $rank): int
    {
        return intdiv($rank, 2) % 3;
    }

    public static function binomeForRank(int $rank): int
    {
        return self::offsetForRank($rank) + 1;
    }

    /**
     * Couverture J/N par jour pour un roster + grid (employee_id => [7 valeurs]).
     *
     * @param  array<int, array<int, string>>  $grid  employee_id => [7 valeurs]
     * @param  int[]  $employeeIds
     * @return array{J: int[], N: int[]}
     */
    public static function coverage(array $grid, array $employeeIds): array
    {
        $J = [];
        $N = [];
        for ($d = 0; $d < 7; $d++) {
            $j = 0;
            $n = 0;
            foreach ($employeeIds as $id) {
                $s = $grid[$id][$d] ?? '';
                if ($s === 'J') {
                    $j++;
                }
                if ($s === 'N') {
                    $n++;
                }
            }
            $J[] = $j;
            $N[] = $n;
        }

        return ['J' => $J, 'N' => $N];
    }

    /**
     * Corps de l'e-mail de diffusion, même format que emailBody() du prototype.
     *
     * @param  string[]  $dates  7 dates ISO de la semaine
     * @param  string[]  $values  7 valeurs J/N/R/ABS pour cet employé
     */
    public static function emailBody(Employee $employee, $room, array $dates, array $values): string
    {
        $lines = [];
        foreach ($values as $d => $s) {
            $txt = match ($s) {
                'J' => 'Jour   '.self::HOURS['J'],
                'N' => 'Nuit   '.self::HOURS['N'],
                'ABS' => 'Absence',
                default => 'Repos',
            };
            $dayLabel = str_pad(self::DAYS[$d], 9);
            $dateLabel = str_pad(self::fmtShort($dates[$d]), 9);
            $lines[] = "  {$dayLabel} {$dateLabel}  {$txt}";
        }
        $linesStr = implode("\n", $lines);

        $nb = count(array_filter($values, fn ($s) => $s === 'J' || $s === 'N'));
        $firstName = explode(' ', $employee->name)[0];
        $roomName = $room->name;
        $sep = '─────────────────────────────────────────';

        return <<<TXT
        Bonjour {$firstName},

        Voici votre planning – Control Room {$roomName}
        Semaine du {$dates[0]} au {$dates[6]} ({$nb} vacations)
        {$sep}
        {$linesStr}
        {$sep}
        Jour = 07h30–17h30   ·   Nuit = 17h30–07h30

        Toute demande de permission doit être soumise au moins 48 h à l'avance.
        Merci de confirmer la bonne réception.

        Cordialement,
        Thara Services Madagascar
        (+261) 32 72 336 43 – contact@thara-services.mg
        TXT;
    }
}
