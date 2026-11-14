<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Uploader;
use App\Core\Validator;
use App\Models\User;
use App\Services\AuditService;
use Throwable;

/**
 * Handles the authenticated user's profile: personal info, avatar upload, password, PIN,
 * live theme preference (Auto/Light/Dark), and revoking other remembered devices.
 */
final class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $this->authorize('profile', 'view');
        $user = Auth::user() ?? [];

        return $this->view('profile/index', [
            'pageTitle' => 'Mon profil',
            'activeTab' => 'more',
            'backUrl' => '/more',
            'backLabel' => 'Plus',
            'user' => $user,
        ]);
    }

    public function updateInfo(Request $request): Response
    {
        $this->authorize('profile', 'update');
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|min:2|max:80',
            'last_name' => 'required|min:2|max:80',
            'phone' => 'nullable|max:32',
            'emergency_contact_name' => 'nullable|max:120',
            'emergency_contact_phone' => 'nullable|max:32',
            'emergency_contact_relation' => 'nullable|max:80',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors('/profile', $request->all(), $validator->errors(), 'Veuillez corriger les champs signalés.');
        }

        $userModel = new User();
        $updates = [
            'first_name' => (string) $request->input('first_name'),
            'last_name' => (string) $request->input('last_name'),
            'phone' => (string) $request->input('phone', ''),
            'emergency_contact_name' => (string) $request->input('emergency_contact_name', ''),
            'emergency_contact_phone' => (string) $request->input('emergency_contact_phone', ''),
            'emergency_contact_relation' => (string) $request->input('emergency_contact_relation', ''),
        ];

        $userModel->update((int) $user['id'], $updates);
        Auth::refreshUser();
        AuditService::record('profile.update_info', 'users', (int) $user['id'], $user, $updates);

        Session::flash('toast_success', 'Vos coordonnées ont été enregistrées.');
        return Response::redirect('/profile');
    }

    public function updateAvatar(Request $request): Response
    {
        $this->authorize('profile', 'update');
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $file = $request->file('avatar');
        $validator = Validator::make(['avatar' => $file], [
            'avatar' => 'required|file|image|max_size:5120',
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                $err = $validator->firstErrors()['avatar'] ?? 'Image invalide.';
                return Response::jsonError('VALIDATION_ERROR', $err, 422);
            }
            return $this->backWithErrors('/profile', [], $validator->errors(), 'Photo de profil invalide.');
        }

        try {
            /** @var array<string, mixed> $file */
            $uploaded = Uploader::uploadImage($file, 'avatars', 512, 180);
            $userModel = new User();
            $userModel->update((int) $user['id'], ['avatar_path' => $uploaded['thumb']]);
            Auth::refreshUser();
            AuditService::record(
                'profile.update_avatar',
                'users',
                (int) $user['id'],
                ['avatar_path' => $user['avatar_path'] ?? null],
                ['avatar_path' => $uploaded['thumb']]
            );

            if ($request->wantsJson()) {
                return Response::jsonSuccess([
                    'avatar_url' => '/media/' . $uploaded['thumb'],
                    'message' => 'Photo de profil mise à jour.',
                ]);
            }

            Session::flash('toast_success', 'Votre photo de profil a été mise à jour.');
            return Response::redirect('/profile');
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return Response::jsonError('UPLOAD_FAILED', $e->getMessage(), 400);
            }
            Session::flash('toast_error', $e->getMessage());
            return Response::redirect('/profile');
        }
    }

    public function updatePassword(Request $request): Response
    {
        $this->authorize('profile', 'update');
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $currentPassword = (string) $request->input('current_password', '');
        if (!Auth::verifySecret($currentPassword, (string) ($user['password_hash'] ?? ''))) {
            return $this->backWithErrors(
                '/profile',
                [],
                ['current_password' => ['Votre mot de passe actuel est incorrect.']],
                'Votre mot de passe actuel est incorrect.'
            );
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|min:10|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors('/profile', [], $validator->errors(), 'Le nouveau mot de passe ne respecte pas les critères.');
        }

        $userModel = new User();
        $userModel->update((int) $user['id'], [
            'password_hash' => Auth::hashSecret((string) $request->input('password')),
        ]);
        Auth::refreshUser();
        AuditService::record('profile.update_password', 'users', (int) $user['id']);

        Session::flash('toast_success', 'Votre mot de passe a été modifié.');
        return Response::redirect('/profile');
    }

    public function updatePin(Request $request): Response
    {
        $this->authorize('profile', 'update');
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $validator = Validator::make($request->all(), [
            'pin' => 'required|regex:/^\d{4,6}$/|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors(
                '/profile',
                [],
                $validator->errors(),
                'Le code PIN doit comporter 4 à 6 chiffres identiques dans les deux champs.'
            );
        }

        $userModel = new User();
        $userModel->update((int) $user['id'], [
            'pin_hash' => Auth::hashSecret((string) $request->input('pin')),
        ]);
        Auth::refreshUser();
        AuditService::record('profile.update_pin', 'users', (int) $user['id']);

        Session::flash('toast_success', 'Votre code PIN personnel a été enregistré.');
        return Response::redirect('/profile');
    }

    public function updateTheme(Request $request): Response
    {
        $this->authorize('profile', 'update');
        $user = Auth::user();
        if ($user === null) {
            return Response::jsonError('UNAUTHENTICATED', 'Non connecté.', 401);
        }

        $theme = (string) $request->input('theme', 'auto');
        if (!in_array($theme, ['auto', 'light', 'dark'], true)) {
            if ($request->wantsJson()) {
                return Response::jsonError('INVALID_THEME', 'Thème invalide.', 422);
            }
            return Response::redirect('/profile');
        }

        $userModel = new User();
        $userModel->update((int) $user['id'], ['theme' => $theme]);
        Auth::refreshUser();
        AuditService::record('profile.update_theme', 'users', (int) $user['id'], ['theme' => $user['theme']], ['theme' => $theme]);

        if ($request->wantsJson()) {
            return Response::jsonSuccess([
                'theme' => $theme,
                'message' => 'Thème appliqué immédiatement.',
            ]);
        }

        Session::flash('toast_success', 'Préférence d’affichage enregistrée.');
        return Response::redirect('/profile');
    }

    public function revokeDevices(Request $request): Response
    {
        $this->authorize('profile', 'update');
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $revoked = Auth::revokeOtherDevices((int) $user['id']);
        AuditService::record('profile.revoke_other_devices', 'users', (int) $user['id'], null, ['revoked_count' => $revoked]);

        Session::flash('toast_success', 'Les autres appareils connectés ont été déconnectés.');
        return Response::redirect('/profile');
    }
}
