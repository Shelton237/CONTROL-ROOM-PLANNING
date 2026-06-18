<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Support\AbsenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AbsenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_requested_more_than_48h_in_advance_is_enregistree(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $employee = Employee::factory()->rotation()->create();
        $service = new AbsenceService();

        // début le 2026-01-04 08:00 -> 72h d'écart > 48h
        $absence = $service->submitPermission($employee, '2026-01-04', '2026-01-04', 'voyage');

        $this->assertSame('enregistree', $absence->status);
        $this->assertSame('permission', $absence->type);

        Carbon::setTestNow();
    }

    public function test_permission_requested_less_than_48h_in_advance_is_refusee(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $employee = Employee::factory()->rotation()->create();
        $service = new AbsenceService();

        // début le lendemain -> 24h d'écart < 48h
        $absence = $service->submitPermission($employee, '2026-01-02', '2026-01-02', 'urgence');

        $this->assertSame('refusee', $absence->status);

        Carbon::setTestNow();
    }

    public function test_refused_permission_does_not_impact_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $employee = Employee::factory()->rotation()->create();
        $service = new AbsenceService();
        $service->submitPermission($employee, '2026-01-02', '2026-01-02', 'urgence');

        $this->assertFalse($employee->absences()->first()->coversDate('2026-01-02') && $employee->absences()->first()->status === 'enregistree');
        $this->assertSame('refusee', $employee->absences()->first()->status);

        Carbon::setTestNow();
    }

    public function test_manager_declared_absence_is_always_enregistree_and_immediate(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $employee = Employee::factory()->rotation()->create();
        $service = new AbsenceService();

        // déclarée le jour même, sans délai
        $absence = $service->declareAbsence($employee, '2026-01-01', '2026-01-01', 'maladie');

        $this->assertSame('enregistree', $absence->status);
        $this->assertSame('absence', $absence->type);
        $this->assertTrue($absence->coversDate('2026-01-01'));

        Carbon::setTestNow();
    }
}
