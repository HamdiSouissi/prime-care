<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\User;
use App\Services\AuditService;
use App\Services\MailService;

/**
 * Team management controller (admin only): list, instant search, view sheet, create with
 * one-time generated initial password, update, and activate/deactivate with audit logging.
 */
final class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('users', 'view');

        $search = (string) $request->query('q', '');
        $userModel = new User();
        $members = $userModel->allTeamMembers($search);

        $creds = Session::flash('generated_credentials');

        return $this->view('users/index', [
            'pageTitle' => 'Équipe',
            'activeTab' => 'team',
            'users' => $members,
            'members' => $members,
            'search' => $search,
            'generatedCredentials' => $creds,
            'generatedPassword' => $creds,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('users', 'create');

        return $this->view('users/form', [
            'pageTitle' => 'Nouveau membre',
            'activeTab' => 'team',
            'backUrl' => '/users',
            'backLabel' => 'Équipe',
            'member' => null,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->authorize('users', 'create');

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|min:2|max:80',
            'last_name' => 'required|min:2|max:80',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|max:32',
            'role' => 'required|in:admin,coordinator,caregiver,viewer',
            'contract_type' => 'nullable|max:50',
            'weekly_hours' => 'nullable|numeric',
            'hourly_rate' => 'nullable|numeric',
            'hired_at' => 'nullable|date',
            'pin' => 'nullable|regex:/^\d{4,6}$/',
            'emergency_contact_name' => 'nullable|max:120',
            'emergency_contact_phone' => 'nullable|max:32',
            'emergency_contact_relation' => 'nullable|max:80',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors(
                '/users/create',
                $request->all(),
                $validator->errors(),
                'Veuillez vérifier les informations saisies.'
            );
        }

        // Generate a strong initial password displayed once to the administrator
        $initialPassword = 'Soin!' . random_int(1000, 9999) . chr(random_int(65, 90)) . chr(random_int(97, 122));
        $pinPlain = trim((string) $request->input('pin', ''));
        if ($pinPlain === '') {
            $pinPlain = (string) random_int(1000, 9999);
        }

        $colors = ['#5E5CE6', '#00C7BE', '#FF9500', '#AF52DE', '#FF2D55', '#007AFF', '#34C759'];
        $colorHex = (string) ($request->input('color_hex') ?: $colors[array_rand($colors)]);

        $userModel = new User();
        $data = [
            'patient_id' => 1,
            'first_name' => (string) $request->input('first_name'),
            'last_name' => (string) $request->input('last_name'),
            'email' => mb_strtolower((string) $request->input('email')),
            'phone' => (string) $request->input('phone', ''),
            'role' => (string) $request->input('role', 'caregiver'),
            'password_hash' => Auth::hashSecret($initialPassword),
            'pin_hash' => Auth::hashSecret($pinPlain),
            'color_hex' => $colorHex,
            'contract_type' => (string) $request->input('contract_type', 'CDI'),
            'weekly_hours' => $request->input('weekly_hours') !== '' ? (float) $request->input('weekly_hours') : 35.0,
            'hourly_rate' => $request->input('hourly_rate') !== '' ? (float) $request->input('hourly_rate') : null,
            'hired_at' => $request->input('hired_at') !== '' ? (string) $request->input('hired_at') : null,
            'emergency_contact_name' => (string) $request->input('emergency_contact_name', ''),
            'emergency_contact_phone' => (string) $request->input('emergency_contact_phone', ''),
            'emergency_contact_relation' => (string) $request->input('emergency_contact_relation', ''),
            'notes' => (string) $request->input('notes', ''),
            'is_active' => 1,
        ];

        $newId = $userModel->create($data);
        AuditService::record('users.create', 'users', $newId, null, $data);

        $htmlMail = '<h2>Bienvenue dans l’équipe Prime Care</h2>'
            . '<p>Bonjour ' . e($data['first_name']) . ',</p>'
            . '<p>Votre compte a été créé sur Prime Care. Voici votre mot de passe initial temporaire :</p>'
            . '<p><strong>' . e($initialPassword) . '</strong> (Code PIN : ' . e($pinPlain) . ')</p>';
        MailService::send($data['email'], 'Prime Care — Votre accès à l’application', $htmlMail, "Mot de passe initial : {$initialPassword}");

        Session::flash('generated_credentials', [
            'name' => $data['first_name'] . ' ' . $data['last_name'],
            'email' => $data['email'],
            'password' => $initialPassword,
            'pin' => $pinPlain,
        ]);
        Session::flash('toast_success', 'Le compte de ' . $data['first_name'] . ' a été créé.');

        return Response::redirect('/users/' . $newId);
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorize('users', 'view');

        $userModel = new User();
        $member = $userModel->findOrFail((int) $id);
        $creds = Session::flash('generated_credentials');

        return $this->view('users/show', [
            'pageTitle' => $member['first_name'] . ' ' . $member['last_name'],
            'activeTab' => 'team',
            'backUrl' => '/users',
            'backLabel' => 'Équipe',
            'member' => $member,
            'canViewRate' => Auth::can('users', 'view_rate'),
            'generatedCredentials' => $creds,
            'generatedPassword' => $creds,
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->authorize('users', 'update');

        $userModel = new User();
        $member = $userModel->findOrFail((int) $id);

        return $this->view('users/form', [
            'pageTitle' => 'Modifier ' . $member['first_name'],
            'activeTab' => 'team',
            'backUrl' => '/users/' . $member['id'],
            'backLabel' => 'Fiche',
            'member' => $member,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->authorize('users', 'update');

        $memberId = (int) $id;
        $userModel = new User();
        $before = $userModel->findOrFail($memberId);

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|min:2|max:80',
            'last_name' => 'required|min:2|max:80',
            'email' => 'required|email|unique:users,email,' . $memberId,
            'phone' => 'nullable|max:32',
            'role' => 'required|in:admin,coordinator,caregiver,viewer',
            'contract_type' => 'nullable|max:50',
            'weekly_hours' => 'nullable|numeric',
            'hourly_rate' => 'nullable|numeric',
            'hired_at' => 'nullable|date',
            'pin' => 'nullable|regex:/^\d{4,6}$/',
            'emergency_contact_name' => 'nullable|max:120',
            'emergency_contact_phone' => 'nullable|max:32',
            'emergency_contact_relation' => 'nullable|max:80',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors(
                '/users/' . $memberId . '/edit',
                $request->all(),
                $validator->errors(),
                'Veuillez vérifier les champs signalés.'
            );
        }

        $updates = [
            'first_name' => (string) $request->input('first_name'),
            'last_name' => (string) $request->input('last_name'),
            'email' => mb_strtolower((string) $request->input('email')),
            'phone' => (string) $request->input('phone', ''),
            'role' => (string) $request->input('role'),
            'color_hex' => (string) $request->input('color_hex', $before['color_hex']),
            'contract_type' => (string) $request->input('contract_type', ''),
            'weekly_hours' => $request->input('weekly_hours') !== '' ? (float) $request->input('weekly_hours') : 0.0,
            'hourly_rate' => $request->input('hourly_rate') !== '' ? (float) $request->input('hourly_rate') : null,
            'hired_at' => $request->input('hired_at') !== '' ? (string) $request->input('hired_at') : null,
            'emergency_contact_name' => (string) $request->input('emergency_contact_name', ''),
            'emergency_contact_phone' => (string) $request->input('emergency_contact_phone', ''),
            'emergency_contact_relation' => (string) $request->input('emergency_contact_relation', ''),
            'notes' => (string) $request->input('notes', ''),
        ];

        $pinPlain = trim((string) $request->input('pin', ''));
        if ($pinPlain !== '') {
            $updates['pin_hash'] = Auth::hashSecret($pinPlain);
        }

        $userModel->update($memberId, $updates);
        AuditService::record('users.update', 'users', $memberId, $before, $updates);

        Session::flash('toast_success', 'La fiche de ' . $updates['first_name'] . ' a été enregistrée.');
        return Response::redirect('/users/' . $memberId);
    }

    public function toggleStatus(Request $request, string $id): Response
    {
        $this->authorize('users', 'toggle_status');

        $memberId = (int) $id;
        $userModel = new User();
        $member = $userModel->findOrFail($memberId);

        if ($memberId === Auth::id()) {
            Session::flash('toast_error', 'Vous ne pouvez pas désactiver votre propre compte administrateur.');
            return Response::redirect('/users/' . $memberId);
        }

        $newStatus = (int) ($member['is_active'] ?? 1) === 1 ? 0 : 1;
        $userModel->update($memberId, ['is_active' => $newStatus]);

        AuditService::record(
            $newStatus === 1 ? 'users.activate' : 'users.deactivate',
            'users',
            $memberId,
            ['is_active' => $member['is_active']],
            ['is_active' => $newStatus]
        );

        $msg = $newStatus === 1
            ? 'Le compte de ' . $member['first_name'] . ' a été réactivé.'
            : 'Le compte de ' . $member['first_name'] . ' a été désactivé.';
        Session::flash('toast_success', $msg);

        return Response::redirect('/users/' . $memberId);
    }
}
