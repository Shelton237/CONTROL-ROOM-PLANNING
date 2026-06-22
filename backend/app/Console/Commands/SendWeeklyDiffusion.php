<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Support\DiffusionService;
use App\Support\PlanningEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Envoie automatiquement les e-mails de diffusion du planning à J-1 du début de chaque
 * semaine (dimanche, pour la semaine qui commence le lendemain), à toutes les salles.
 *
 * Planifiée pour tourner tous les jours (voir routes/console.php) : ne fait rien tant que
 * "demain" n'est pas un lundi, sauf avec --force (utile pour rejouer manuellement).
 */
class SendWeeklyDiffusion extends Command
{
    protected $signature = 'planning:send-weekly-diffusion {--force} {--week=}';

    protected $description = "Envoie les e-mails de diffusion du planning à J-1 du début de la semaine suivante, pour toutes les salles";

    public function handle(DiffusionService $diffusionService): int
    {
        $tomorrow = Carbon::now()->addDay();

        if (! $this->option('force') && $tomorrow->dayOfWeekIso !== Carbon::MONDAY) {
            $this->info("Rien à envoyer aujourd'hui (J-1 du {$tomorrow->toDateString()} n'est pas un lundi).");

            return self::SUCCESS;
        }

        $weekStart = $this->option('week')
            ? PlanningEngine::mondayOf($this->option('week'))
            : $tomorrow->toDateString();

        $rooms = Room::all();
        if ($rooms->isEmpty()) {
            $this->info('Aucune salle à traiter.');

            return self::SUCCESS;
        }

        foreach ($rooms as $room) {
            $result = $diffusionService->sendForRoom($room, $weekStart);
            $sentCount = count($result['sent']);
            $failedCount = count($result['failed']);

            $this->info("Salle {$room->name} (semaine du {$weekStart}) : {$sentCount} envoyé(s), {$failedCount} échec(s).");

            foreach ($result['failed'] as $failure) {
                $this->warn("  - {$failure['name']} : {$failure['error']}");
            }
        }

        return self::SUCCESS;
    }
}
