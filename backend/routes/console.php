<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// J-1 du début de chaque semaine (la commande elle-même ne fait rien si "demain" n'est
// pas un lundi) : diffusion des plannings à tous les agents de toutes les salles.
Schedule::command('planning:send-weekly-diffusion')
    ->dailyAt('08:00')
    ->withoutOverlapping();
