<?php

namespace Tests\Feature;

use App\Mail\WelcomeAgentMail;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmployeeAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_employee_with_email_creates_an_agent_account_and_sends_credentials(): void
    {
        Mail::fake();

        $manager = User::factory()->manager()->create();
        $room = Room::factory()->create();

        $response = $this->actingAs($manager, 'sanctum')->postJson('/api/employees', [
            'room_id' => $room->id,
            'name' => 'Nouvel Agent',
            'email' => 'nouvel.agent@thara-services.mg',
            'type' => 'rotation',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('account.created', true);
        $response->assertJsonPath('account.email_sent', true);
        $this->assertNotEmpty($response->json('account.password'));

        $employeeId = $response->json('id');
        $this->assertDatabaseHas('users', [
            'email' => 'nouvel.agent@thara-services.mg',
            'role' => 'agent',
            'employee_id' => $employeeId,
        ]);

        Mail::assertSent(WelcomeAgentMail::class, function ($mail) {
            return $mail->loginEmail === 'nouvel.agent@thara-services.mg';
        });
    }

    public function test_creating_an_employee_without_email_does_not_create_an_account(): void
    {
        Mail::fake();

        $manager = User::factory()->manager()->create();
        $room = Room::factory()->create();

        $response = $this->actingAs($manager, 'sanctum')->postJson('/api/employees', [
            'room_id' => $room->id,
            'name' => 'Sans Email',
            'type' => 'rotation',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('account.created', false);
        $response->assertJsonPath('account.reason', 'no_email');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('users', 1); // seulement le manager
    }

    public function test_creating_an_employee_with_an_already_used_email_does_not_duplicate_the_account(): void
    {
        Mail::fake();

        $manager = User::factory()->manager()->create();
        $room = Room::factory()->create();
        User::factory()->create(['email' => 'existant@thara-services.mg']);

        $response = $this->actingAs($manager, 'sanctum')->postJson('/api/employees', [
            'room_id' => $room->id,
            'name' => 'Doublon',
            'email' => 'existant@thara-services.mg',
            'type' => 'rotation',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('account.created', false);
        $response->assertJsonPath('account.reason', 'email_already_used');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('users', 2); // manager + l'utilisateur existant, pas de 3e
    }

    public function test_account_is_still_created_when_mail_sending_fails(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $manager = User::factory()->manager()->create();
        $room = Room::factory()->create();

        $response = $this->actingAs($manager, 'sanctum')->postJson('/api/employees', [
            'room_id' => $room->id,
            'name' => 'Resilient',
            'email' => 'resilient@thara-services.mg',
            'type' => 'rotation',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('account.created', true);
        $response->assertJsonPath('account.email_sent', false);

        $this->assertDatabaseHas('users', [
            'email' => 'resilient@thara-services.mg',
            'role' => 'agent',
        ]);
    }
}
