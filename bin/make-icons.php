<?php

declare(strict_types=1);

/**
 * Generates derived brand PNG assets (PWA icons, Apple touch icon, maskable icon,
 * favicon, Android notification badge, Open Graph image, and iOS standalone splash screens)
 * from the official Prime Care brand PNG files using GD.
 *
 * IMPORTANT: Never modifies the 7 official SVG/PNG source files in public/assets/brand/.
 */

$brandDir = dirname(__DIR__) . '/public/assets/brand';

$degradePath = $brandDir . '/prime-care-icone-degrade.png';
$transparentPath = $brandDir . '/prime-care-icone-transparent.png';
$completPath = $brandDir . '/prime-care-logo-complet.png';

foreach ([$degradePath, $transparentPath, $completPath] as $requiredFile) {
    if (!is_file($requiredFile)) {
        fwrite(STDERR, "Missing source brand file: {$requiredFile}\n");
        exit(1);
    }
}

/**
 * Create a true-color GD image.
 */
function createCanvas(int $width, int $height, bool $transparent = true, array $bgRgb = [255, 255, 255]): GdImage
{
    $img = imagecreatetruecolor($width, $height);
    if ($transparent) {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $clear = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefilledrectangle($img, 0, 0, $width, $height, $clear);
    } else {
        imagealphablending($img, true);
        $bg = imagecolorallocate($img, $bgRgb[0], $bgRgb[1], $bgRgb[2]);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);
    }
    return $img;
}

// 1. Load prime-care-icone-degrade.png (1024x1024)
$degrade = imagecreatefrompng($degradePath);
$degW = imagesx($degrade);
$degH = imagesy($degrade);

// 1a. Center 820x820 crop (fully opaque zone without transparent rounded corners)
$cropSize = 820;
$cropX = (int) round(($degW - $cropSize) / 2);
$cropY = (int) round(($degH - $cropSize) / 2);

// icon-maskable-512.png (512x512, opaque)
$maskable512 = createCanvas(512, 512, false, [94, 92, 230]);
imagecopyresampled($maskable512, $degrade, 0, 0, $cropX, $cropY, 512, 512, $cropSize, $cropSize);
imagepng($maskable512, $brandDir . '/icon-maskable-512.png', 9);
imagedestroy($maskable512);

// apple-touch-icon.png (180x180, opaque)
$apple180 = createCanvas(180, 180, false, [94, 92, 230]);
imagecopyresampled($apple180, $degrade, 0, 0, $cropX, $cropY, 180, 180, $cropSize, $cropSize);
imagepng($apple180, $brandDir . '/apple-touch-icon.png', 9);
imagedestroy($apple180);

// 1b. Full icon with rounded corners preserved: icon-512.png & icon-192.png
foreach ([512 => 'icon-512.png', 192 => 'icon-192.png'] as $size => $filename) {
    $canvas = createCanvas($size, $size, true);
    imagecopyresampled($canvas, $degrade, 0, 0, 0, 0, $size, $size, $degW, $degH);
    imagepng($canvas, $brandDir . '/' . $filename, 9);
    imagedestroy($canvas);
}

// 1c. iOS Standalone Splash Screens (#0A1633 background + centered gradient icon) (§4 bis.2)
$splashDir = $brandDir . '/splash';
if (!is_dir($splashDir)) {
    mkdir($splashDir, 0775, true);
}

$splashSizes = [
    [750, 1334, 'splash-750x1334.png'],
    [1170, 2532, 'splash-1170x2532.png'],
    [1179, 2556, 'splash-1179x2556.png'],
    [1290, 2796, 'splash-1290x2796.png'],
];

foreach ($splashSizes as [$sw, $sh, $sFile]) {
    $splash = createCanvas($sw, $sh, false, [10, 22, 51]); // #0A1633 Bleu nuit
    imagealphablending($splash, true);
    $iconSize = (int) round(min($sw, $sh) * 0.24);
    $ix = (int) round(($sw - $iconSize) / 2);
    $iy = (int) round(($sh - $iconSize) / 2);
    imagecopyresampled($splash, $degrade, $ix, $iy, 0, 0, $iconSize, $iconSize, $degW, $degH);
    imagepng($splash, $splashDir . '/' . $sFile, 9);
    imagedestroy($splash);
}

imagedestroy($degrade);

// 2. Load prime-care-icone-transparent.png for favicon-32.png and badge-96.png
$transparent = imagecreatefrompng($transparentPath);
$transW = imagesx($transparent);
$transH = imagesy($transparent);

// favicon-32.png (32x32)
$fav32 = createCanvas(32, 32, true);
imagecopyresampled($fav32, $transparent, 0, 0, 0, 0, 32, 32, $transW, $transH);
imagepng($fav32, $brandDir . '/favicon-32.png', 9);
imagedestroy($fav32);

// badge-96.png (96x96 white monochrome on transparent background)
$badge96 = createCanvas(96, 96, true);
$temp96 = createCanvas(96, 96, true);
imagecopyresampled($temp96, $transparent, 0, 0, 0, 0, 96, 96, $transW, $transH);
for ($y = 0; $y < 96; $y++) {
    for ($x = 0; $x < 96; $x++) {
        $rgba = imagecolorat($temp96, $x, $y);
        $alpha = ($rgba >> 24) & 0x7F;
        if ($alpha < 127) {
            $whiteAlpha = imagecolorallocatealpha($badge96, 255, 255, 255, $alpha);
            imagesetpixel($badge96, $x, $y, $whiteAlpha);
        }
    }
}
imagepng($badge96, $brandDir . '/badge-96.png', 9);
imagedestroy($temp96);
imagedestroy($badge96);
imagedestroy($transparent);

// 3. Open Graph image og-image.png (1200x630, #F4F7FC background, centered full logo)
$complet = imagecreatefrompng($completPath);
$compW = imagesx($complet);
$compH = imagesy($complet);

$og = createCanvas(1200, 630, false, [244, 247, 252]); // #F4F7FC
imagealphablending($og, true);

$targetW = 680;
$targetH = (int) round($compH * ($targetW / $compW));
$dstX = (int) round((1200 - $targetW) / 2);
$dstY = (int) round((630 - $targetH) / 2);

imagecopyresampled($og, $complet, $dstX, $dstY, 0, 0, $targetW, $targetH, $compW, $compH);
imagepng($og, $brandDir . '/og-image.png', 9);
imagedestroy($og);
imagedestroy($complet);

echo "All brand icons and iOS splash screens generated successfully in {$brandDir}\n";
