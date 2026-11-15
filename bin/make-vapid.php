<?php

declare(strict_types=1);

/**
 * Prime Care — Générateur de paire de clés VAPID ECDSA (prime256v1)
 * Usage : php bin/make-vapid.php [--force]
 * Génère `storage/keys/vapid.json` (hors Git).
 */

require_once dirname(__DIR__) . '/app/Helpers/bootstrap.php';

use App\Services\WebPushService;

$force = in_array('--force', $argv ?? [], true);
$keys = WebPushService::generateVapidKeys($force);
$path = WebPushService::vapidFilePath();

echo "Clés VAPID ECDSA (prime256v1) prêtes dans : {$path}\n";
echo "Clé publique (Base64URL) : {$keys['publicKey']}\n";
echo "Sujet VAPID             : {$keys['subject']}\n";
