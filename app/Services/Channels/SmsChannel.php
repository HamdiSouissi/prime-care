<?php

declare(strict_types=1);

namespace App\Services\Channels;

use App\Core\Config;

/**
 * SmsChannel — Point d'extension pour l'envoi de notifications par SMS.
 * Désactivé par défaut conformément au périmètre Phase 3 (aucun service tiers payant).
 */
final class SmsChannel
{
    /**
     * Indique si le canal SMS est activé dans la configuration.
     */
    public static function isEnabled(): bool
    {
        return (bool) Config::get('sms.enabled', false);
    }

    /**
     * Point d'extension pour l'envoi d'un SMS.
     * Retourne false avec explication lorsque le canal est désactivé.
     *
     * @return array{ok: bool, error: ?string}
     */
    public static function send(string $phoneNumber, string $message): array
    {
        if (!self::isEnabled()) {
            return [
                'ok' => false,
                'error' => 'Canal SMS désactivé par défaut (extension SmsChannel prête).',
            ];
        }

        // Point d'extension pour un connecteur opérateur futur
        return [
            'ok' => true,
            'error' => null,
        ];
    }
}
