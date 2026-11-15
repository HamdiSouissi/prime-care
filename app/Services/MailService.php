<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * MailService — Envoi d'e-mails HTML via PHPMailer (SMTP configuré dans config.php)
 * avec gabarit HTML sobre (en-tête Bleu nuit #0A1633, logo complet en PNG prime-care-logo-complet.png,
 * badge de gravité, lien direct vers l'alerte et crédit Prime Vision Solutions)
 * et doublage systématique dans `storage/logs/mail.log` en développement.
 */
final class MailService
{
    /**
     * Envoie un e-mail HTML (doublé dans `storage/logs/mail.log` en dev/test, envoyé via PHPMailer SMTP si driver === 'smtp').
     */
    public static function send(string $to, string $subject, string $bodyHtml, string $bodyText = '', string $recipientName = ''): bool
    {
        $appUrl = rtrim((string) Config::get('app.url', 'http://localhost:8000'), '/');
        $logoPngUrl = $appUrl . '/assets/brand/prime-care-logo-complet.png';
        $plainText = $bodyText !== '' ? $bodyText : trim(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $bodyHtml)));

        $wrappedHtml = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>'
            . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8')
            . '</title></head>'
            . '<body style="margin:0;padding:24px;font-family:-apple-system,BlinkMacSystemFont,Inter,Segoe UI,Roboto,sans-serif;background:#F4F7FC;color:#0F172A;">'
            . '<div style="max-width:560px;margin:0 auto;background:#FFFFFF;border-radius:24px;overflow:hidden;border:1px solid #E2E8F0;">'
            . '<div style="background:#0A1633;padding:22px 28px;text-align:center;">'
            . '<div style="background:#FFFFFF;padding:8px 16px;border-radius:14px;display:inline-block;">'
            . '<img src="' . htmlspecialchars($logoPngUrl, ENT_QUOTES, 'UTF-8') . '" alt="PRIME CARE" width="160" style="height:auto;display:block;">'
            . '</div>'
            . '<div style="color:rgba(255,255,255,0.82);font-size:12px;margin-top:10px;">Suivi de soins à domicile · M. Henri Benali</div>'
            . '</div>'
            . '<div style="padding:28px;">'
            . $bodyHtml
            . '</div>'
            . '<div style="background:#F8FAFC;padding:16px 28px;border-top:1px solid #E2E8F0;text-align:center;">'
            . '<p style="font-size:12px;color:#64748B;margin:0;">'
            . 'Application créée par <a href="https://primevisionsolutions.fr" target="_blank" rel="noopener" style="color:#5E5CE6;text-decoration:none;font-weight:600;">Prime Vision Solutions</a>'
            . '</p>'
            . '</div></div></body></html>';

        $logDir = (string) Config::get('paths.logs', dirname(__DIR__, 2) . '/storage/logs');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        // Prepare PHPMailer instance (3 vendored files in public/assets/vendor/phpmailer/)
        $mailer = new PHPMailer(true);
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $fromAddress = (string) Config::get('mail.from_address', 'ne-pas-repondre@primecare.test');
        $fromName = (string) Config::get('mail.from_name', 'Prime Care — Suivi de soins');

        try {
            $mailer->setFrom($fromAddress, $fromName);
            $mailer->addAddress($to, $recipientName);
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $wrappedHtml;
            $mailer->AltBody = $plainText;
            $mailer->preSend();
            $mimePreview = $mailer->getSentMIMEMessage();
        } catch (PHPMailerException $e) {
            $mimePreview = 'PHPMailer preSend error: ' . $e->getMessage();
        }

        $entry = sprintf(
            "=== MAIL [%s] ===\nTo: %s\nSubject: %s\nText: %s\nHTML:\n%s\nMIME-Length: %d\n=== END MAIL ===\n\n",
            gmdate('Y-m-d\TH:i:s\Z'),
            $to,
            $subject,
            $plainText,
            $wrappedHtml,
            strlen($mimePreview)
        );
        @file_put_contents($logDir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);

        $driver = (string) Config::get('mail.driver', 'log');
        if ($driver === 'smtp') {
            try {
                $mailer->isSMTP();
                $mailer->Host = (string) Config::get('mail.host', 'smtp.example.org');
                $mailer->Port = (int) Config::get('mail.port', 587);
                $username = (string) Config::get('mail.username', '');
                $password = (string) Config::get('mail.password', '');
                if ($username !== '') {
                    $mailer->SMTPAuth = true;
                    $mailer->Username = $username;
                    $mailer->Password = $password;
                }
                $encryption = (string) Config::get('mail.encryption', 'tls');
                if ($encryption === 'tls') {
                    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($encryption === 'ssl') {
                    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                }
                return $mailer-> postSend();
            } catch (PHPMailerException) {
                return false;
            }
        }

        return true;
    }

    /**
     * Construit et envoie un e-mail d'alerte formaté avec niveau de gravité et bouton d'accès direct.
     *
     * @param array<string, mixed> $alert
     */
    public static function sendAlertEmail(string $to, string $recipientName, array $alert): bool
    {
        $level = (string) ($alert['level'] ?? 'warning');
        $title = (string) ($alert['title'] ?? 'Alerte Prime Care');
        $message = (string) ($alert['message'] ?? '');
        $targetUrl = (string) ($alert['target_url'] ?? '/alerts');

        $appUrl = rtrim((string) Config::get('app.url', 'http://localhost:8000'), '/');
        $fullLink = str_starts_with($targetUrl, 'http') ? $targetUrl : ($appUrl . $targetUrl);

        $badgeBg = match ($level) {
            'critical' => '#FEE2E2',
            'warning' => '#FEF3C7',
            default => '#E0E7FF',
        };
        $badgeColor = match ($level) {
            'critical' => '#B91C1C',
            'warning' => '#B45309',
            default => '#4338CA',
        };
        $levelLabel = match ($level) {
            'critical' => 'URGENCE CRITIQUE',
            'warning' => 'ATTENTION REQUISE',
            default => 'INFORMATION',
        };

        $subjectPrefix = match ($level) {
            'critical' => '[URGENT · Prime Care] ',
            'warning' => '[Alerte · Prime Care] ',
            default => '[Info · Prime Care] ',
        };

        $html = '<div style="margin-bottom:16px;">'
            . '<span style="display:inline-block;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:0.05em;background:' . $badgeBg . ';color:' . $badgeColor . ';">'
            . htmlspecialchars($levelLabel, ENT_QUOTES, 'UTF-8')
            . '</span>'
            . '</div>'
            . '<h2 style="margin:0 0 12px;font-size:19px;color:#0A1633;">'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '</h2>'
            . '<p style="margin:0 0 20px;font-size:15px;line-height:1.55;color:#334155;">'
            . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'))
            . '</p>'
            . '<div style="margin:24px 0 8px;">'
            . '<a href="' . htmlspecialchars($fullLink, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 22px;border-radius:14px;background:#5E5CE6;color:#FFFFFF;text-decoration:none;font-weight:600;font-size:14px;">'
            . 'Ouvrir dans Prime Care'
            . '</a>'
            . '</div>';

        return self::send($to, $subjectPrefix . $title, $html, $title . ' — ' . $message, $recipientName);
    }
}
