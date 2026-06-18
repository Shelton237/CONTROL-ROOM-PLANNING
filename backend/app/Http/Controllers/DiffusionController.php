<?php

namespace App\Http\Controllers;

use App\Mail\PlanningMail;
use App\Models\Room;
use App\Support\PlanningEngine;
use App\Support\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DiffusionController extends Controller
{
    public function __construct(private ScheduleService $scheduleService)
    {
    }

    public function preview(Request $request, Room $room)
    {
        $week = $request->query('week') ?: PlanningEngine::mondayOf(now()->format('Y-m-d'));

        return response()->json($this->buildMessages($room, $week));
    }

    public function send(Request $request, Room $room)
    {
        $data = $request->validate(['week' => ['required', 'date']]);
        $messages = $this->buildMessages($room, $data['week']);

        $results = [];
        foreach ($messages as $m) {
            if (empty($m['email'])) {
                $results[] = ['employee_id' => $m['employee_id'], 'name' => $m['name'], 'status' => 'failed', 'reason' => 'no_email'];

                continue;
            }

            try {
                Mail::to($m['email'])->send(new PlanningMail($m['subject'], $m['body']));
                $results[] = ['employee_id' => $m['employee_id'], 'name' => $m['name'], 'status' => 'sent'];
            } catch (Throwable $e) {
                $results[] = ['employee_id' => $m['employee_id'], 'name' => $m['name'], 'status' => 'failed', 'reason' => $e->getMessage()];
            }
        }

        return response()->json($results);
    }

    private function buildMessages(Room $room, string $week): array
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
}
