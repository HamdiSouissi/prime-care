<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\User;
use App\Services\AuditService;
use App\Services\MailService;

/**
 * Handles password login, trusted-device PIN pad login, password reset flow, and logout.
 */
final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        $trustedProfiles = Auth::trustedProfiles();
        $forcePasswordMode = $request->query('mode') === 'password';

        return $this->view('auth/login', [
            'pageTitle' => t('auth.login.title'),
            'trustedProfiles' => $trustedProfiles,
            'forcePasswordMode' => $forcePasswordMode,
        ], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        $email = mb_strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $remember = (bool) $request->input('remember', false);
        $ip = $request->ip();

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors('/login?mode=password', $request->all(), $validator->errors());
        }

        if (RateLimiter::tooManyAttempts($email, $ip)) {
            return $this->backWithErrors(
                '/login?mode=password',
                ['email' => $email],
                ['email' => [t('auth.login.failed')]],
                t('auth.login.locked')
            );
        }

        if (!Auth::attempt($email, $password, $remember)) {
            RateLimiter::recordAttempt($email, $ip, false);
            return $this->backWithErrors(
                '/login?mode=password',
                ['email' => $email],
                ['email' => [t('auth.login.failed')]],
                t('auth.login.failed')
            );
        }

        RateLimiter::recordAttempt($email, $ip, true);

        Session::flash('show_launch_loader', true);
        Session::flash('toast_success', 'Bienvenue, ' . ((string) (Auth::user()['first_name'] ?? '')) . '.');

        $intended = (string) Session::get('intended_url', '/');
        Session::forget('intended_url');
        return Response::redirect($intended !== '' ? $intended : '/');
    }

    public function showPin(Request $request): Response
    {
        $trustedProfiles = Auth::trustedProfiles();
        if ($trustedProfiles === []) {
            return Response::redirect('/login');
        }

        $requestedSelector = strtolower(trim((string) $request->query('selector', '')));
        $activeProfile = $trustedProfiles[0];
        foreach ($trustedProfiles as $profile) {
            if ($profile['selector'] === $requestedSelector) {
                $activeProfile = $profile;
                break;
            }
        }

        return $this->view('auth/pin', [
            'pageTitle' => t('auth.pin.greeting', ['name' => (string) $activeProfile['first_name']]),
            'trustedProfiles' => $trustedProfiles,
            'deviceUser' => $activeProfile,
        ], 'layouts/auth');
    }

    public function loginPin(Request $request): Response
    {
        // Never trust or use user_id from request input; require a valid trusted-device selector
        $selector = strtolower(trim((string) $request->input('selector', '')));
        $pin = trim((string) $request->input('pin', ''));
        $ip = $request->ip();

        $result = Auth::attemptTrustedPin($selector, $pin, null, $ip);

        if (!$result['ok']) {
            if ($result['code'] === 'RATE_LIMITED') {
                if ($request->wantsJson()) {
                    return Response::jsonError('RATE_LIMITED', $result['message'], 429);
                }
                Session::flash('toast_error', $result['message']);
                return Response::redirect('/login?mode=password');
            }

            if ($request->wantsJson()) {
                return Response::jsonError('INVALID_PIN', $result['message'], 401);
            }
            Session::flash('toast_error', $result['message']);
            return Response::redirect('/login');
        }

        AuditService::record('auth.login_pin', 'users', Auth::id(), null, [
            'method' => 'pin',
            'selector' => $selector,
        ]);
        Session::flash('show_launch_loader', true);

        if ($request->wantsJson()) {
            return Response::jsonSuccess(['redirect' => '/']);
        }

        return Response::redirect('/');
    }

    public function switchUser(Request $request): Response
    {
        return Response::redirect('/login?mode=password');
    }

    public function showForgot(Request $request): Response
    {
        return $this->view('auth/forgot', [
            'pageTitle' => t('auth.forgot.title'),
        ], 'layouts/auth');
    }

    public function sendForgot(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors('/forgot-password', $request->all(), $validator->errors());
        }

        $email = mb_strtolower(trim((string) $request->input('email', '')));
        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if ($user !== null && (int) ($user['is_active'] ?? 0) === 1) {
            $token = $userModel->createPasswordReset($email);
            $appUrl = rtrim((string) Config::get('app.url', 'http://localhost:8000'), '/');
            $resetLink = $appUrl . '/reset-password/' . $token;

            $html = '<h2 style="font-size:20px;margin:0 0 12px;">Réinitialisation de votre mot de passe</h2>'
                . '<p style="font-size:15px;line-height:1.5;color:#1C1C1E;">Bonjour ' . e($user['first_name']) . ',</p>'
                . '<p style="font-size:15px;line-height:1.5;color:#1C1C1E;">Vous avez demandé à définir un nouveau mot de passe sur <strong>Prime Care</strong>. Ce lien est valable pendant 1 heure :</p>'
                . '<p style="text-align:center;margin:24px 0;"><a href="' . e($resetLink) . '" style="display:inline-block;background:#5E5CE6;color:#FFFFFF;text-decoration:none;padding:12px 24px;border-radius:12px;font-weight:600;">Choisir un nouveau mot de passe</a></p>'
                . '<p style="font-size:13px;color:rgba(60,60,67,.62);">Lien direct : ' . e($resetLink) . '</p>';

            MailService::send($email, 'Prime Care — Réinitialisation de votre mot de passe', $html, "Lien : {$resetLink}");
            AuditService::record('auth.password_reset_requested', 'users', (int) $user['id']);
        }

        Session::flash('toast_success', t('auth.forgot.sent'));
        return Response::redirect('/login');
    }

    public function showReset(Request $request, string $token): Response
    {
        $userModel = new User();
        $resetRow = $userModel->findValidPasswordReset($token);
        if ($resetRow === null) {
            Session::flash('toast_error', t('auth.reset.invalid_token'));
            return Response::redirect('/forgot-password');
        }

        return $this->view('auth/reset', [
            'pageTitle' => t('auth.reset.title'),
            'token' => $token,
            'email' => (string) $resetRow['email'],
        ], 'layouts/auth');
    }

    public function resetPassword(Request $request, string $token): Response
    {
        $userModel = new User();
        $resetRow = $userModel->findValidPasswordReset($token);
        if ($resetRow === null) {
            Session::flash('toast_error', t('auth.reset.invalid_token'));
            return Response::redirect('/forgot-password');
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|min:10|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors('/reset-password/' . $token, [], $validator->errors());
        }

        $user = $userModel->findByEmail((string) $resetRow['email']);
        if ($user === null) {
            Session::flash('toast_error', t('auth.reset.invalid_token'));
            return Response::redirect('/forgot-password');
        }

        $newHash = Auth::hashSecret((string) $request->input('password'));
        $userModel->update((int) $user['id'], ['password_hash' => $newHash]);
        $userModel->markPasswordResetUsed((int) $resetRow['id']);
        Auth::revokeAllDevicesForUser((int) $user['id'], true);

        AuditService::record('auth.password_reset_completed', 'users', (int) $user['id'], null, null, 1, (int) $user['id']);
        Session::flash('toast_success', t('auth.reset.success'));
        return Response::redirect('/login');
    }

    public function logout(Request $request): Response
    {
        $userId = Auth::id();
        if ($userId !== null) {
            AuditService::record('auth.logout', 'users', $userId);
        }

        Auth::logout();
        Session::flash('toast_info', t('auth.logout.success'));

        return Response::redirect('/login');
    }
}
