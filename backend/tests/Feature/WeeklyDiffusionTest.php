<?php

namespace Tests\Feature;

use App\Mail\PlanningMail;
use App\Models\Employee;
use App\Models\Room;
use App\Support\DiffusionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WeeklyDiffusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_diffusion_service_groups_sent_and_failed_results(): void
    {
        Mail::fake();

        $room = Room::factory()->create();
        Employee::factory()->for($room)->rotation()->create(['email' => 'agent@thara-services.mg']);
        Employee::factory()->for($room)->rotation(1)->create(['email' => null]);

        $service = app(DiffusionService::class);
        $result = $service->sendForRoom($room, '2026-01-05');

        $this->assertCount(1, $result['sent']);
        $this->assertCount(1, $result['failed']);
        $this->assertSame('no_email', $result['failed'][0]['error']);
        $this->assertTrue($result['sent'][0]['success']);

        Mail::assertSent(PlanningMail::class, 1);
    }

    public function test_command_does_nothing_when_tomorrow_is_not_monday(): void
    {
        Mail::fake();
        // un mardi : "demain" (mercredi) n'est pas un lundi.
        Carbon::setTestNow(Carbon::parse('2026-01-06 08:00:00'));

        $room = Room::factory()->create();
        Employee::factory()->for($room)->rotation()->create(['email' => 'agent@thara-services.mg']);

        $this->artisan('planning:send-weekly-diffusion')->assertExitCode(0);

        Mail::assertNothingSent();

        Carbon::setTestNow();
    }

    public function test_command_sends_diffusion_when_tomorrow_is_monday(): void
    {
        Mail::fake();
        // un dimanche : "demain" (lundi) est le début de la semaine suivante.
        Carbon::setTestNow(Carbon::parse('2026-01-04 08:00:00'));

        $room = Room::factory()->create();
        Employee::factory()->for($room)->rotation()->create(['email' => 'agent@thara-services.mg']);

        $this->artisan('planning:send-weekly-diffusion')->assertExitCode(0);

        Mail::assertSent(PlanningMail::class, 1);

        Carbon::setTestNow();
    }

    public function test_command_force_option_sends_regardless_of_day(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-01-06 08:00:00'));

        $room = Room::factory()->create();
        Employee::factory()->for($room)->rotation()->create(['email' => 'agent@thara-services.mg']);

        $this->artisan('planning:send-weekly-diffusion', ['--force' => true, '--week' => '2026-01-05'])
            ->assertExitCode(0);

        Mail::assertSent(PlanningMail::class, 1);

        Carbon::setTestNow();
    }
}
