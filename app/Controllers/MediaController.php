<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Services\Policy;
use finfo;
use RuntimeException;

/**
 * Serves user-uploaded images and prescription documents from storage/uploads/
 * after verifying authentication and role-based permissions.
 */
final class MediaController extends Controller
{
    public function show(Request $request, string $folder, string $filename): Response
    {
        $role = Auth::role() ?? 'viewer';
        $safeFolder = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder) ?: '';
        $safeFile = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename) ?: '';

        // Check resource permission according to folder
        $resource = match ($safeFolder) {
            'meals' => 'meals',
            'measures' => 'vitals',
            'medications', 'prescriptions' => 'medications',
            'notes' => 'notes',
            default => 'dashboard',
        };

        if (!Policy::allows($role, $resource, 'view')) {
            throw new RuntimeException('Accès refusé à ce document médical.', 403);
        }

        $uploadsRoot = (string) Config::get('paths.uploads', dirname(__DIR__, 2) . '/storage/uploads');
        $fullPath = $uploadsRoot . '/' . $safeFolder . '/' . $safeFile;

        if ($safeFolder === '' || $safeFile === '' || !is_file($fullPath)) {
            throw new RuntimeException('Média introuvable.', 404);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($fullPath);

        return Response::download($fullPath, $mime, $safeFile, true);
    }
}
