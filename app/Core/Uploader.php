<?php

declare(strict_types=1);

namespace App\Core;

use finfo;
use GdImage;
use RuntimeException;

/**
 * Secure file and image uploader with finfo MIME verification, EXIF orientation correction,
 * GD re-encoding (stripping all EXIF/GPS metadata), and square thumbnail generation.
 */
final class Uploader
{
    private const ALLOWED_IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const ALLOWED_DOCUMENT_MIMES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Validate and store a prescription or medical document (PDF, JPG, PNG, WebP).
     * Images are re-encoded via GD to strip EXIF; PDFs are verified via finfo + %PDF header.
     *
     * @param array<string, mixed> $file
     * @return array{filename: string, mime: string, original_name: string, size: int}
     */
    public static function uploadDocument(array $file, string $subDir = 'prescriptions'): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Erreur lors du téléversement du document.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            throw new RuntimeException('Fichier temporaire introuvable.');
        }

        $maxBytes = (int) Config::get('security.max_upload_bytes', 5 * 1024 * 1024);
        $size = (int) ($file['size'] ?? filesize($tmpPath));
        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException('La taille du document dépasse la limite autorisée (5 Mo).');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpPath);
        if (!isset(self::ALLOWED_DOCUMENT_MIMES[$mime])) {
            throw new RuntimeException('Format de document non autorisé. Utilisez PDF, JPG ou PNG.');
        }

        $originalName = preg_replace('/[^a-zA-Z0-9._ -]/', '', basename((string) ($file['name'] ?? 'ordonnance'))) ?: 'ordonnance';

        if (isset(self::ALLOWED_IMAGE_MIMES[$mime])) {
            $imgRes = self::uploadImage($file, $subDir, 1600, 400);
            return [
                'filename' => $imgRes['filename'],
                'mime' => $imgRes['mime'],
                'original_name' => $originalName,
                'size' => $imgRes['size'],
            ];
        }

        // Verify %PDF- magic header
        $head = (string) file_get_contents($tmpPath, false, null, 0, 5);
        if ($head !== '%PDF-') {
            throw new RuntimeException('Le fichier PDF fourni est invalide.');
        }

        $uploadsRoot = (string) Config::get('paths.uploads', dirname(__DIR__, 2) . '/storage/uploads');
        $safeSubDir = preg_replace('/[^a-zA-Z0-9_-]/', '', $subDir) ?: 'prescriptions';
        $targetDir = $uploadsRoot . '/' . $safeSubDir;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $randomBase = bin2hex(random_bytes(16));
        $relFilename = $safeSubDir . '/' . $randomBase . '.pdf';
        $fullPath = $uploadsRoot . '/' . $relFilename;

        if (!copy($tmpPath, $fullPath)) {
            throw new RuntimeException('Impossible d’enregistrer le document PDF.');
        }

        return [
            'filename' => $relFilename,
            'mime' => 'application/pdf',
            'original_name' => $originalName,
            'size' => (int) filesize($fullPath),
        ];
    }

    /**
     * Validate, re-encode (stripping EXIF), and store an uploaded image + square thumbnail.
     *
     * @param array<string, mixed> $file
     * @return array{filename: string, thumb: string, mime: string, size: int}
     */
    public static function uploadImage(
        array $file,
        string $subDir = 'avatars',
        int $maxDimension = 800,
        int $thumbDimension = 200
    ): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Erreur lors du téléversement du fichier.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            throw new RuntimeException('Fichier temporaire introuvable.');
        }

        $maxBytes = (int) Config::get('security.max_upload_bytes', 5 * 1024 * 1024);
        $size = (int) ($file['size'] ?? filesize($tmpPath));
        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException('La taille de l’image dépasse la limite autorisée (5 Mo).');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpPath);
        if (!isset(self::ALLOWED_IMAGE_MIMES[$mime])) {
            throw new RuntimeException('Format d’image non autorisé. Utilisez JPG, PNG ou WebP.');
        }

        $sourceImg = self::loadImage($tmpPath, $mime);
        $orientedImg = self::applyExifOrientation($sourceImg, $tmpPath, $mime);

        $uploadsRoot = (string) Config::get('paths.uploads', dirname(__DIR__, 2) . '/storage/uploads');
        $safeSubDir = preg_replace('/[^a-zA-Z0-9_-]/', '', $subDir) ?: 'avatars';
        $targetDir = $uploadsRoot . '/' . $safeSubDir;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $randomBase = bin2hex(random_bytes(16));
        $mainFilename = $safeSubDir . '/' . $randomBase . '.jpg';
        $thumbFilename = $safeSubDir . '/' . $randomBase . '_thumb.jpg';

        $mainImage = self::resizeProportional($orientedImg, $maxDimension);
        $thumbImage = self::cropSquare($orientedImg, $thumbDimension);

        $mainFullPath = $uploadsRoot . '/' . $mainFilename;
        $thumbFullPath = $uploadsRoot . '/' . $thumbFilename;

        imagejpeg($mainImage, $mainFullPath, 88);
        imagejpeg($thumbImage, $thumbFullPath, 86);

        imagedestroy($mainImage);
        imagedestroy($thumbImage);
        imagedestroy($orientedImg);

        return [
            'filename' => $mainFilename,
            'thumb' => $thumbFilename,
            'mime' => 'image/jpeg',
            'size' => (int) filesize($mainFullPath),
        ];
    }

    private static function loadImage(string $path, string $mime): GdImage
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if (!$img instanceof GdImage) {
            throw new RuntimeException('Impossible de décoder l’image envoyée.');
        }

        return $img;
    }

    private static function applyExifOrientation(GdImage $img, string $path, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $img;
        }

        $exif = @exif_read_data($path);
        if (!is_array($exif) || !isset($exif['Orientation'])) {
            return $img;
        }

        $orientation = (int) $exif['Orientation'];
        $rotated = match ($orientation) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };

        return $rotated instanceof GdImage ? $rotated : $img;
    }

    private static function resizeProportional(GdImage $src, int $maxDim): GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);

        if ($w <= $maxDim && $h <= $maxDim) {
            $dstW = $w;
            $dstH = $h;
        } elseif ($w >= $h) {
            $dstW = $maxDim;
            $dstH = max(1, (int) round($h * ($maxDim / $w)));
        } else {
            $dstH = $maxDim;
            $dstW = max(1, (int) round($w * ($maxDim / $h)));
        }

        $canvas = imagecreatetruecolor($dstW, $dstH);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $dstW, $dstH, $white);
        imagecopyresampled($canvas, $src, 0, 0, 0, 0, $dstW, $dstH, $w, $h);

        return $canvas;
    }

    private static function cropSquare(GdImage $src, int $size): GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $minSide = min($w, $h);
        $srcX = (int) round(($w - $minSide) / 2);
        $srcY = (int) round(($h - $minSide) / 2);

        $canvas = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $white);
        imagecopyresampled($canvas, $src, 0, 0, $srcX, $srcY, $size, $size, $minSide, $minSide);

        return $canvas;
    }
}
