<?php

/*
 * Script de migration à usage unique, pour un déploiement FTP-only (sans
 * terminal SSH). À appeler UNE FOIS depuis le navigateur :
 *   https://ton-domaine/deploy-migrate.php?token=LE_TOKEN_DEFINI_DANS_ENV
 * puis SUPPRIMER ce fichier via FTP immédiatement après usage.
 *
 * Protégé par DEPLOY_MIGRATE_TOKEN (.env) : si la variable n'est pas
 * définie, le script refuse toute requête (désactivé par défaut).
 */

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');

$expectedToken = env('DEPLOY_MIGRATE_TOKEN');

if (empty($expectedToken) || ($_GET['token'] ?? '') !== $expectedToken) {
    http_response_code(403);
    echo "Acces refuse.\n";
    echo "Definis DEPLOY_MIGRATE_TOKEN dans .env (une valeur longue et aleatoire),\n";
    echo "puis appelle ce script avec ?token=<la-meme-valeur>.\n";
    exit;
}

Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
echo Illuminate\Support\Facades\Artisan::output();

echo "\nTermine. SUPPRIME CE FICHIER MAINTENANT (FTP) pour des raisons de securite.\n";
