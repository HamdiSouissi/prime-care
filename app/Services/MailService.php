<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Mail delivery service writing formatted emails (with PNG logo header and publisher credit)
 * to storage/logs/mail.log in development mode.
 */
final class MailService
{
    /**
     * Send an email message (logged to storage/logs/mail.log in development).
     */
    public static function send(string $to, string $subject, string $bodyHtml, string $bodyText = ''): bool
    {
        $appUrl = rtrim((string) Config::get('app.url', 'http://localhost:8000'), '/');
        $logoPngUrl = $appUrl . '/assets/brand/prime-care-logo-complet.png';

        $wrappedHtml = '<!doctype html><html lang="fr"><body style="font-family:-apple-system,BlinkMacSystemFont,Inter,sans-serif;background:#F2F2F7;padding:24px;color:#1C1C1E;">'
            . '<div style="max-width:520px;margin:0 auto;background:#FFFFFF;border-radius:20px;padding:28px;">'
            . '<div style="text-align:center;margin-bottom:20px;">'
            . '<img src="' . htmlspecialchars($logoPngUrl, ENT_QUOTES, 'UTF-8') . '" alt="PRIME CARE" width="180" style="height:auto;">'
            . '</div>'
            . $bodyHtml
            . '<hr style="border:none;border-top:1px solid rgba(60,60,67,.12);margin:24px 0 16px;">'
            . '<p style="font-size:12px;color:rgba(60,60,67,.62);text-align:center;margin:0;">'
            . 'Application créée par <a href="https://primevisionsolutions.fr" target="_blank" rel="noopener" style="color:#5E5CE6;text-decoration:none;">Prime Vision Solutions</a>'
            . '</p></div></body></html>';

        $logDir = (string) Config::get('paths.logs', dirname(__DIR__, 2) . '/storage/logs');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $entry = sprintf(
            "=== MAIL [%s] ===\nTo: %s\nSubject: %s\nText: %s\nHTML:\n%s\n=== END MAIL ===\n\n",
            gmdate('Y-m-d\TH:i:s\Z'),
            $to,
            $subject,
            $bodyText !== '' ? $bodyText : strip_tags($bodyHtml),
            $wrappedHtml
        );

        file_put_contents($logDir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
        return true;
    }
}
