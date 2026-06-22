<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Support\DiffusionService;
use App\Support\PlanningEngine;
use Illuminate\Http\Request;

class DiffusionController extends Controller
{
    public function __construct(private DiffusionService $diffusionService)
    {
    }

    public function preview(Request $request, Room $room)
    {
        $week = $request->query('week') ?: PlanningEngine::mondayOf(now()->format('Y-m-d'));

        return response()->json($this->diffusionService->buildMessages($room, $week));
    }

    public function send(Request $request, Room $room)
    {
        $data = $request->validate(['week' => ['required', 'date']]);

        return response()->json($this->diffusionService->sendForRoom($room, $data['week']));
    }
}
