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

    public function test_agent_requested_permission_more_than_48h_in_advance_is_en_attente(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $employee = Employee::factory()->rotation()->create();
        $service = new AbsenceService();

        // début le 2026-01-04 08:00 -> 72h d'écart > 48h, mais soumise par l'agent :
        // reste en attente, le manager doit encore valider ou rejeter.
        $absence = $service->requestPermission($employee, '2026-01-04', '2026-01-04', 'voyage');

        $this->assertSame('en_attente', $absence->status);
        $this->assertSame('permission', $absence->type);

        Carbon::setTestNow();
    }

    public function test_agent_requested_permission_less_than_48h_in_advance_is_refusee_immediately(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $employee = Employee::factory()->rotation()->create();
        $service = new AbsenceService();

        $absence = $service->requestPermission($employee, '2026-01-02', '2026-01-02', 'urgence');

        // refus définitif, pas besoin d'une validation manager : le délai est intangible.
        $this->assertSame('refusee', $absence->status);

        Carbon::setTestNow();
    }

    public function test_manager_can_approve_a_pending_request(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $employee = Employee::factory()->rotation()->create();
        $service = new AbsenceService();
        $absence = $service->requestPermission($employee, '2026-02-01', '2026-02-01', 'perso');

        $this->assertSame('en_attente', $absence->status);

        $approved = $service->approve($absence);

        $this->assertSame('enregistree', $approved->status);
        $this->assertSame('enregistree', $absence->fresh()->status);

        Carbon::setTestNow();
    }

    public function test_manager_can_reject_a_pending_request(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $employee = Employee::factory()->rotation()->create();
        $service = new AbsenceService();
        $absence = $service->requestPermission($employee, '2026-02-01', '2026-02-01', 'perso');

        $rejected = $service->reject($absence);

        $this->assertSame('refusee', $rejected->status);
        $this->assertSame('refusee', $absence->fresh()->status);

        Carbon::setTestNow();
    }
}
