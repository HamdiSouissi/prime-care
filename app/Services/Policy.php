<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Central RBAC authorization policy matrix mapping resource -> action -> allowed roles.
 */
final class Policy
{
    public const ROLES = ['admin', 'coordinator', 'caregiver', 'viewer'];

    /**
     * Complete role-based permission matrix (Cahier des charges §2).
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private const MATRIX = [
        'dashboard' => [
            'view' => ['admin', 'coordinator', 'caregiver', 'viewer'],
        ],
        'patient' => [
            'view' => ['admin', 'coordinator', 'caregiver', 'viewer'],
            'update' => ['admin'],
        ],
        'patient_contacts' => [
            'view' => ['admin', 'coordinator', 'caregiver', 'viewer'],
            'create' => ['admin'],
            'update' => ['admin'],
            'delete' => ['admin'],
        ],
        'users' => [
            'view' => ['admin'],
            'create' => ['admin'],
            'update' => ['admin'],
            'toggle_status' => ['admin'],
            'view_rate' => ['admin'],
        ],
        'profile' => [
            'view' => ['admin', 'coordinator', 'caregiver', 'viewer'],
            'update' => ['admin', 'coordinator', 'caregiver', 'viewer'],
        ],
        'vitals' => [
            'view' => ['admin', 'coordinator', 'caregiver', 'viewer'],
            'create' => ['admin', 'coordinator', 'caregiver'],
            'update' => ['admin', 'coordinator', 'caregiver'],
            'delete' => ['admin'],
            'configure' => ['admin'],
        ],
        'medications' => [
            'view' => ['admin', 'coordinator', 'caregiver', 'viewer'],
            'validate_intake' => ['admin', 'coordinator', 'caregiver'],
            'create' => ['admin'],
            'update' => ['admin'],
            'delete' => ['admin'],
        ],
        'meals' => [
            'view' => ['admin', 'coordinator', 'caregiver', 'viewer'],
            'create' => ['admin', 'coordinator', 'caregiver'],
            'update' => ['admin', 'coordinator', 'caregiver'],
            'delete' => ['admin'],
        ],
        'notes' => [
            'view' => ['admin', 'coordinator', 'caregiver', 'viewer'],
            'create' => ['admin', 'coordinator', 'caregiver'],
            'update' => ['admin', 'coordinator', 'caregiver'],
            'delete' => ['admin'],
        ],
        'schedule' => [
            'view' => ['admin', 'coordinator', 'caregiver', 'viewer'],
            'manage' => ['admin', 'coordinator'],
        ],
        'timeclock' => [
            'view' => ['admin', 'coordinator', 'caregiver'],
            'punch' => ['admin', 'coordinator', 'caregiver'],
            'validate' => ['admin', 'coordinator'],
        ],
        'alerts' => [
            'view' => ['admin', 'coordinator', 'caregiver', 'viewer'],
            'acknowledge' => ['admin', 'coordinator', 'caregiver'],
            'configure' => ['admin'],
        ],
        'documents' => [
            'view' => ['admin', 'coordinator', 'caregiver', 'viewer'],
            'create' => ['admin'],
            'delete' => ['admin'],
        ],
        'reports' => [
            'view' => ['admin', 'coordinator', 'viewer'],
            'export' => ['admin', 'coordinator', 'viewer'],
        ],
        'settings' => [
            'view' => ['admin'],
            'update' => ['admin'],
        ],
        'audit' => [
            'view' => ['admin'],
        ],
        'design' => [
            'view' => ['admin'],
        ],
    ];

    /**
     * Determine whether the given role is allowed to perform an action on a resource.
     */
    public static function allows(string $role, string $resource, string $action): bool
    {
        $allowedRoles = self::MATRIX[$resource][$action] ?? [];
        return in_array($role, $allowedRoles, true);
    }

    /**
     * Return the full permission matrix for inspection and unit testing.
     *
     * @return array<string, array<string, array<int, string>>>
     */
    public static function matrix(): array
    {
        return self::MATRIX;
    }
}
