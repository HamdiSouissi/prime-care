<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use finfo;
use RuntimeException;

/**
 * Serves user-uploaded images and prescription documents from storage/uploads/ after verifying authentication.
 */
final class MediaController extends Controller
{
    public function show(Request $request, string $folder, string $filename): Response
    {
        // Reject any path traversal attempt, null bytes, or double-encoded sequences
        $rawUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $decodedOnce = rawurldecode($folder . '/' . $filename . '/' . $rawUri);
        $decodedTwice = rawurldecode($decodedOnce);
        if (
            str_contains($folder, "\0") ||
            str_contains($filename, "\0") ||
            str_contains($rawUri, "\0") ||
            str_contains($rawUri, '%00') ||
            str_contains($decodedOnce, '..') ||
            str_contains($decodedTwice, '..') ||
            str_contains($folder, '..') ||
            str_contains($filename, '..') ||
            !in_array($folder, ['avatars', 'meals', 'medications', 'prescriptions', 'documents', 'notes', 'vitals'], true)
        ) {
            throw new RuntimeException('Chemin de fichier invalide ou interdit.', 403);
        }

        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename) || str_starts_with($filename, '.')) {
            throw new RuntimeException('Nom de fichier invalide.', 403);
        }

        $uploadsRoot = (string) Config::get('paths.uploads', dirname(__DIR__, 2) . '/storage/uploads');
        $realRoot = realpath($uploadsRoot);
        $candidatePath = $uploadsRoot . '/' . $folder . '/' . $filename;
        $realFile = is_file($candidatePath) ? realpath($candidatePath) : false;

        if ($realRoot === false || $realFile === false || !str_starts_with($realFile, $realRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Média introuvable.', 404);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($realFile);

        return Response::download($realFile, $mime, $filename, true);
    }

    public function mealPhoto(Request $request, string $file): Response
    {
        $this->authorize('meals', 'view');
        return $this->show($request, 'meals', $file);
    }

    public function medicationPhoto(Request $request, string $file): Response
    {
        $this->authorize('medications', 'view');
        return $this->show($request, 'medications', $file);
    }

    public function prescriptionDocument(Request $request, string $file): Response
    {
        $this->authorize('medications', 'view');
        return $this->show($request, 'prescriptions', $file);
    }
}
