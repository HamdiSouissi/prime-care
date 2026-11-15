<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\ApiMedicalController;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Uploader;
use App\Services\AttentionService;
use App\Services\EditWindowService;
use App\Services\HandoverService;
use App\Services\MealService;
use App\Services\QrCodeService;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

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

        // 5. E4: Meal creation with photo only (empty description -> "Repas photographié") vs neither (422)
        Auth::attempt('fatou@primecare.test', 'Soin!2026');
        $apiCtrl = new ApiMedicalController();

        $emptyMealReq = new Request([], ['description' => '', 'portion_eaten' => '100'], [], ['REQUEST_METHOD' => 'POST']);
        $emptyMealRes = $apiCtrl->storeMeal($emptyMealReq);
        $assert(
            $emptyMealRes->status() === 422,
            'E4 MealService/API: repas sans composition ni photo est refusé (422)'
        );

        $tmpImg = tempnam(sys_get_temp_dir(), 'pc_meal_') . '.jpg';
        $im = imagecreatetruecolor(240, 180);
        $bg = imagecolorallocate($im, 10, 22, 51);
        imagefilledrectangle($im, 0, 0, 240, 180, $bg);
        imagejpeg($im, $tmpImg, 85);
        imagedestroy($im);

        $photoOnlyReq = new Request(
            [],
            ['description' => '', 'portion_eaten' => '75'],
            [
                'photo_avant' => [
                    'name' => 'plateau.jpg',
                    'type' => 'image/jpeg',
                    'tmp_name' => $tmpImg,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($tmpImg),
                ],
            ],
            ['REQUEST_METHOD' => 'POST']
        );
        $photoOnlyRes = $apiCtrl->storeMeal($photoOnlyReq);
        @unlink($tmpImg);
        $photoOnlyJson = json_decode($photoOnlyRes->body(), true) ?: [];
        $assert(
            $photoOnlyRes->status() === 201
            && ($photoOnlyJson['ok'] ?? false) === true
            && ($photoOnlyJson['data']['description'] ?? '') === 'Repas photographié'
            && count($photoOnlyJson['data']['photos'] ?? []) === 1,
            'E4 MealService/API: repas avec photo seule est accepté (201) et affiche « Repas photographié »'
        );

        // 6. E2 Uploads:
        // 6a. Faux JPEG contenant du PHP refusé
        $fakePhpJpg = tempnam(sys_get_temp_dir(), 'pc_fake_') . '.jpg';
        file_put_contents($fakePhpJpg, "<?php system(\$_GET['cmd']); ?>");
        $fakeRejected = false;
        try {
            Uploader::uploadImage([
                'name' => 'avatar.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => $fakePhpJpg,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($fakePhpJpg),
            ], 'meals', 1200, 400);
        } catch (Throwable) {
            $fakeRejected = true;
        } finally {
            @unlink($fakePhpJpg);
        }
        $assert($fakeRejected, 'E2 Uploads: faux JPEG contenant du code PHP est refusé par finfo');

        // 6b. Vrai JPEG avec segment EXIF APP1 ré-encodé sans EXIF + miniature carrée 400 px
        $rawGdJpg = tempnam(sys_get_temp_dir(), 'pc_raw_') . '.jpg';
        $exifJpg = tempnam(sys_get_temp_dir(), 'pc_exif_') . '.jpg';
        $srcIm = imagecreatetruecolor(900, 600);
        $c1 = imagecolorallocate($srcIm, 24, 104, 219);
        imagefilledrectangle($srcIm, 0, 0, 900, 600, $c1);
        imagejpeg($srcIm, $rawGdJpg, 90);
        imagedestroy($srcIm);

        // Inject a synthetic APP1 Exif header right after SOI (\xFF\xD8)
        $rawBytes = (string) file_get_contents($rawGdJpg);
        $exifPayload = "Exif\x00\x00II*\x00\x08\x00\x00\x00\x01\x00\x12\x01\x03\x00\x01\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00GPSSecretLocation";
        $app1Segment = "\xFF\xE1" . pack('n', strlen($exifPayload) + 2) . $exifPayload;
        file_put_contents($exifJpg, substr($rawBytes, 0, 2) . $app1Segment . substr($rawBytes, 2));
        @unlink($rawGdJpg);

        $hadExifBefore = str_contains((string) file_get_contents($exifJpg), "Exif\x00\x00");
        $uploaded = Uploader::uploadImage([
            'name' => 'repas_exif.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $exifJpg,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($exifJpg),
        ], 'meals', 1200, 400);
        @unlink($exifJpg);

        $uploadsRoot = (string) Config::get('paths.uploads');
        $savedMainPath = $uploadsRoot . '/' . $uploaded['filename'];
        $savedThumbPath = $uploadsRoot . '/' . $uploaded['thumb'];
        $hasExifAfter = str_contains((string) file_get_contents($savedMainPath), 'GPSSecretLocation');
        $thumbInfo = @getimagesize($savedThumbPath);

        $assert(
            $hadExifBefore === true
            && $hasExifAfter === false
            && is_array($thumbInfo)
            && $thumbInfo[0] === 400
            && $thumbInfo[1] === 400,
            'E2 Uploads: vrai JPEG avec EXIF est ré-encodé sans EXIF et génère une miniature 400×400 px'
        );

        // 6c. Fichier trop lourd (> 5 Mo) refusé
        $oversizedJpg = tempnam(sys_get_temp_dir(), 'pc_big_') . '.jpg';
        file_put_contents($oversizedJpg, $rawBytes);
        $oversizedRejected = false;
        try {
            Uploader::uploadImage([
                'name' => 'huge.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => $oversizedJpg,
                'error' => UPLOAD_ERR_OK,
                'size' => 6 * 1024 * 1024, // 6 MB > 5 MB limit
            ], 'meals', 1200, 400);
        } catch (Throwable) {
            $oversizedRejected = true;
        } finally {
            @unlink($oversizedJpg);
        }
        $assert($oversizedRejected, 'E2 Uploads: fichier trop lourd (> 5 Mo) est refusé');

        // 7. E3 AttentionService: dédoublonnage des transmissions non lues par auteur + phrase de synthèse
        $attAdmin = AttentionService::getCurrentAttentionPoints(1, 1);
        $marieUnreadItems = array_values(array_filter(
            $attAdmin['items'],
            static fn (array $item): bool => ($item['type'] ?? '') === 'unread_handover' && str_contains((string) $item['title'], 'Marie')
        ));
        $assert(
            count($marieUnreadItems) === 1
            && str_contains((string) $marieUnreadItems[0]['title'], 'transmissions non lues de Marie'),
            'E3 AttentionService: plusieurs transmissions non lues de Marie sont dédoublonnées en un seul point'
        );
    }
}
