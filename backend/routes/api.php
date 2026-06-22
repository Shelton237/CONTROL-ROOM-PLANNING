<?php

use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DiffusionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

// ----- Auth -----
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ----- Manager -----
    Route::middleware('role:manager')->group(function () {
        Route::get('/rooms', [RoomController::class, 'index']);
        Route::post('/rooms', [RoomController::class, 'store']);
        Route::patch('/rooms/{room}', [RoomController::class, 'update']);
        Route::delete('/rooms/{room}', [RoomController::class, 'destroy']);

        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::patch('/employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);

        Route::get('/rooms/{room}/schedule', [ScheduleController::class, 'show']);
        Route::patch('/rooms/{room}/schedule', [ScheduleController::class, 'update']);
        Route::post('/rooms/{room}/schedule/reset', [ScheduleController::class, 'reset']);
        Route::post('/rooms/{room}/schedule/loans', [ScheduleController::class, 'addLoan']);
        Route::delete('/rooms/{room}/schedule/loans', [ScheduleController::class, 'removeLoan']);

        Route::get('/absences', [AbsenceController::class, 'index']);
        Route::post('/absences', [AbsenceController::class, 'store']);
        Route::delete('/absences/{absence}', [AbsenceController::class, 'destroy']);
        Route::post('/absences/{absence}/approve', [AbsenceController::class, 'approve']);
        Route::post('/absences/{absence}/reject', [AbsenceController::class, 'reject']);

        Route::post('/permissions', [PermissionController::class, 'store']);

        Route::get('/rooms/{room}/diffusion', [DiffusionController::class, 'preview']);
        Route::post('/rooms/{room}/diffusion/send', [DiffusionController::class, 'send']);
    });

    // ----- Agent (scope = employee_id du user connecté) -----
    Route::middleware('role:agent')->group(function () {
        Route::get('/me/schedule', [ScheduleController::class, 'showMine']);
        Route::get('/me/absences', [AbsenceController::class, 'mine']);
        Route::post('/me/permissions', [PermissionController::class, 'storeMine']);
    });
});
