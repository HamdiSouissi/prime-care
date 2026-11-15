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
 * trusted device activation for PIN login, live theme preference (Auto/Light/Dark),
 * and revoking remembered/trusted devices.
 */
final class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $this->authorize('profile', 'view');
        $user = Auth::user() ?? [];
        $userId = (int) ($user['id'] ?? 0);

        $trustedDevice = $userId > 0 ? Auth::currentBrowserTrustedForUser($userId) : null;
        $notificationSettings = $userId > 0 ? \App\Services\AlertService::getUserNotificationSettings($userId) : [];
        $vapidKeys = \App\Services\WebPushService::getOrCreateVapidKeys();

        $pdo = \App\Core\Database::connection();
        $subStmt = $pdo->prepare('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = :uid AND deleted_at IS NULL');
        $subStmt->execute(['uid' => $userId]);
        $pushSubsCount = (int) $subStmt->fetchColumn();

        return $this->view('profile/index', [
            'pageTitle' => 'Mon profil',
            'activeTab' => 'more',
            'backUrl' => '/more',
            'backLabel' => 'Plus',
            'user' => $user,
            'trustedDevice' => $trustedDevice,
            'deviceTrusted' => $trustedDevice !== null,
            'authenticatedViaPassword' => Auth::authenticatedViaPassword(),
            'notificationSettings' => $notificationSettings,
            'alertTypes' => \App\Services\AlertService::TYPES,
            'vapidPublicKey' => $vapidKeys['publicKey'],
            'pushSubsCount' => $pushSubsCount,
        ]);
    }

    public function updateNotifications(Request $request): Response
    {
        $this->authorize('profile', 'update');
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        \App\Services\AlertService::updateUserNotificationSettings((int) $user['id'], $request->all());
        Session::flash('toast_success', 'Vos préférences d’alertes et heures calmes ont été enregistrées.');
        return Response::redirect('/profile');
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
        Auth::revokeAllDevicesForUser((int) $user['id'], false);
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

        $enableOnDevice = (bool) $request->input('enable_on_device', false);
        if ($enableOnDevice && Auth::authenticatedViaPassword()) {
            $label = trim((string) $request->input('device_label', 'Téléphone mobile'));
            Auth::issueTrustedDevice((int) $user['id'], $label !== '' ? $label : 'Téléphone mobile');
            AuditService::record('profile.trust_device', 'users', (int) $user['id'], null, ['device_label' => $label]);
            Session::flash('toast_success', 'Votre code PIN a été enregistré et activé sur cet appareil.');
            return Response::redirect('/profile');
        }

        AuditService::record('profile.update_pin', 'users', (int) $user['id']);
        Session::flash('toast_success', 'Votre code PIN personnel a été enregistré.');
        return Response::redirect('/profile');
    }

    public function trustDevice(Request $request): Response
    {
        $this->authorize('profile', 'update');
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        if (!Auth::authenticatedViaPassword()) {
            Session::flash(
                'toast_error',
                'Par sécurité, reconnectez-vous avec votre mot de passe avant d’activer la connexion par PIN sur cet appareil.'
            );
            return Response::redirect('/profile');
        }

        if (empty($user['pin_hash'])) {
            Session::flash('toast_error', 'Veuillez d’abord définir votre code PIN personnel.');
            return Response::redirect('/profile');
        }

        $label = trim((string) $request->input('device_label', 'Téléphone mobile'));
        Auth::issueTrustedDevice((int) $user['id'], $label !== '' ? $label : 'Téléphone mobile');
        AuditService::record('profile.trust_device', 'users', (int) $user['id'], null, ['device_label' => $label]);

        Session::flash('toast_success', 'La connexion par code PIN est maintenant activée sur cet appareil (90 jours).');
        return Response::redirect('/profile');
    }

    public function revokeTrustedDevice(Request $request): Response
    {
        $this->authorize('profile', 'update');
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $trusted = Auth::currentBrowserTrustedForUser((int) $user['id']);
        if ($trusted !== null) {
            Auth::revokeTrustedDeviceSelector((int) $user['id'], (string) $trusted['selector']);
            AuditService::record('profile.revoke_trusted_device', 'users', (int) $user['id'], null, [
                'selector' => $trusted['selector'],
            ]);
        }

        Session::flash('toast_success', 'La connexion par code PIN a été désactivée sur cet appareil.');
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

        $revoked = Auth::revokeAllDevicesForUser((int) $user['id'], true);
        AuditService::record('profile.revoke_other_devices', 'users', (int) $user['id'], null, ['revoked_count' => $revoked]);

        Session::flash('toast_success', 'Tous les jetons d’appareil et de connexion par PIN ont été révoqués.');
        return Response::redirect('/profile');
    }

    /**
     * GET/POST /profile/export-data — RGPD Art. 20 personal data archive for the logged-in user (Phase 4 §5.2).
     */
    public function exportData(Request $request): Response
    {
        $this->authorize('profile', 'view');
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $res = \App\Services\ExportService::exportUserPersonalDataArchive((int) $user['id'], 1);
        $content = (string) @file_get_contents($res['file_path']);
        @unlink($res['file_path']);

        return new Response($content, 200, [
            'Content-Type' => $res['mime'],
            'Content-Disposition' => 'attachment; filename="' . $res['filename'] . '"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
