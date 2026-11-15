<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\EditWindowService;
use App\Services\HandoverService;
use App\Services\MealService;
use App\Services\QrCodeService;
use DateTimeImmutable;
use DateTimeZone;

final class EditWindowHandoverMealTest
{
    public static function run(callable $assert): void
    {
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $tenMinAgo = $nowUtc->modify('-10 minutes')->format('Y-m-d H:i:s');
        $twoHoursAgo = $nowUtc->modify('-120 minutes')->format('Y-m-d H:i:s');

        $recentRow = ['created_at' => $tenMinAgo, 'created_by' => 3];
        $oldRow = ['created_at' => $twoHoursAgo, 'created_by' => 3];

        // 1. 30-minute caregiver edit window rules
        $caregiverWithin30 = EditWindowService::checkEditPermission($recentRow, 3, 'caregiver', null, $nowUtc);
        $caregiverAfter30 = EditWindowService::checkEditPermission($oldRow, 3, 'caregiver', null, $nowUtc);
        $caregiverOtherAuthor = EditWindowService::checkEditPermission($recentRow, 4, 'caregiver', null, $nowUtc);
        $viewerAttempt = EditWindowService::checkEditPermission($recentRow, 5, 'viewer', null, $nowUtc);
        $adminAfter30NoReason = EditWindowService::checkEditPermission($oldRow, 1, 'admin', '', $nowUtc);
        $adminAfter30WithReason = EditWindowService::checkEditPermission($oldRow, 1, 'admin', 'Erreur de lecture lecteur', $nowUtc);

        $assert($caregiverWithin30['allowed'] === true, 'EditWindowService: aide-soignante peut modifier sa saisie dans les 30 minutes');
        $assert(
            $caregiverAfter30['allowed'] === false && $caregiverAfter30['code'] === 'EDIT_WINDOW_EXPIRED' && $caregiverAfter30['http_status'] === 403,
            'EditWindowService: aide-soignante est bloquée après 30 minutes (403 EDIT_WINDOW_EXPIRED)'
        );
        $assert(
            $caregiverOtherAuthor['allowed'] === false && $caregiverOtherAuthor['code'] === 'NOT_OWNER',
            'EditWindowService: aide-soignante ne peut pas modifier la saisie d’une collègue'
        );
        $assert($viewerAttempt['allowed'] === false && $viewerAttempt['http_status'] === 403, 'EditWindowService: rôle viewer est bloqué en écriture');
        $assert(
            $adminAfter30NoReason['allowed'] === false && $adminAfter30NoReason['code'] === 'REASON_REQUIRED' && $adminAfter30NoReason['http_status'] === 422,
            'EditWindowService: admin après 30 min sans motif est refusé (422 REASON_REQUIRED)'
        );
        $assert(
            $adminAfter30WithReason['allowed'] === true && $adminAfter30WithReason['requires_reason'] === true,
            'EditWindowService: admin après 30 min avec motif obligatoire est autorisé'
        );

        // 2. MealService automatic deduction & linked glycemias
        $assert(MealService::deduceMealType(8) === 'breakfast', 'MealService: 8h déduit Petit-déjeuner (breakfast)');
        $assert(MealService::deduceMealType(12) === 'lunch', 'MealService: 12h déduit Déjeuner (lunch)');
        $assert(MealService::deduceMealType(16) === 'snack', 'MealService: 16h déduit Collation (snack)');
        $assert(MealService::deduceMealType(19) === 'dinner', 'MealService: 19h déduit Dîner (dinner)');

        // 3. HandoverService shift summary & read tracking
        $summary = HandoverService::buildShiftSummary();
        $assert(
            isset($summary['summary_text'], $summary['intakes_given'], $summary['measures_count']),
            'HandoverService: génère le récapitulatif automatique de poste depuis la dernière relève'
        );

        $unreadBefore = HandoverService::getLatestUnreadHandover(3, 1); // Fatou (id=3)
        $assert($unreadBefore !== null, 'HandoverService: Fatou voit la dernière transmission de relève non lue');
        if ($unreadBefore !== null) {
            HandoverService::markAsRead((int) $unreadBefore['id'], 3, 1);
            $unreadAfter = HandoverService::getLatestUnreadHandover(3, 1);
            $assert($unreadAfter === null, 'HandoverService: après « Marquer comme lu », le bandeau disparaît pour Fatou');
        }

        // 4. QrCodeService server-side SVG generation (zero external CDN)
        $qrSvg = QrCodeService::renderSvg('https://primecare.local/?source=pwa', 200);
        $assert(
            str_starts_with($qrSvg, '<svg') && str_contains($qrSvg, 'fill="#0A1633"') && !str_contains($qrSvg, 'style='),
            'QrCodeService: génère un QR code SVG ISO 18004 côté serveur sans attribut style= ni appel externe'
        );
    }
}
