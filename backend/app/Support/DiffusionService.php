<?php

namespace App\Support;

use App\Mail\PlanningMail;
use App\Models\Room;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Construit et envoie les e-mails de diffusion du planning par agent, pour une salle et
 * une semaine donnée. Utilisé à la fois par l'envoi manuel (DiffusionController) et par
 * l'envoi automatique à J-1 du début de semaine (commande planning:send-weekly-diffusion).
 */
class DiffusionService
{
    public function __construct(private ScheduleService $scheduleService)
    {
    }

    /**
     * @return array<int, array{employee_id:int, name:string, email:?string, subject:string, body:string}>
     */
    public function buildMessages(Room $room, string $week): array
    {
        $weekStart = PlanningEngine::mondayOf($week);
        $result = $this->scheduleService->weekSchedule($room, $weekStart);
        $dates = $result['dates'];

        $messages = [];
        foreach ($result['roster'] as $employee) {
            $values = $result['grid'][$employee->id];
            $body = PlanningEngine::emailBody($employee, $room, $dates, $values);
            $subject = "Planning Control Room {$room->name} – semaine du {$dates[0]}";

            $messages[] = [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'subject' => $subject,
                'body' => $body,
            ];
        }

        return $messages;
    }

    /**
     * Envoie réellement les e-mails (best-effort) et regroupe les résultats.
     *
     * @return array{sent: array, failed: array}
     */
    public function sendForRoom(Room $room, string $week): array
    {
        $sent = [];
        $failed = [];

        foreach ($this->buildMessages($room, $week) as $m) {
            if (empty($m['email'])) {
                $failed[] = [
                    'employee_id' => $m['employee_id'],
                    'name' => $m['name'],
                    'email' => $m['email'],
                    'success' => false,
                    'error' => 'no_email',
                ];

                continue;
            }

            try {
                Mail::to($m['email'])->send(new PlanningMail($m['subject'], $m['body']));
                $sent[] = [
                    'employee_id' => $m['employee_id'],
                    'name' => $m['name'],
                    'email' => $m['email'],
                    'success' => true,
                ];
            } catch (Throwable $e) {
                $failed[] = [
                    'employee_id' => $m['employee_id'],
                    'name' => $m['name'],
                    'email' => $m['email'],
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
