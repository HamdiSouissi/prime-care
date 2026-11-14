<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * User model representing caregivers, coordinators, viewers, and administrators.
 */
final class User extends Model
{
    protected string $table = 'users';

    /** @var array<int, string> */
    protected array $fillable = [
        'patient_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'role',
        'password_hash',
        'pin_hash',
        'avatar_path',
        'color_hex',
        'contract_type',
        'weekly_hours',
        'hourly_rate',
        'hired_at',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'notes',
        'theme',
        'locale',
        'is_active',
        'last_login_at',
        'created_by',
    ];

    /**
     * Find an active or inactive user by email address.
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM `users` WHERE LOWER(`email`) = :email AND `deleted_at` IS NULL LIMIT 1'
        );
        $stmt->execute(['email' => mb_strtolower(trim($email))]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * List all non-deleted team members ordered by active status, role, and name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allTeamMembers(?string $search = null): array
    {
        $sql = 'SELECT * FROM `users` WHERE `deleted_at` IS NULL';
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $sql .= ' AND (`first_name` LIKE :q1 OR `last_name` LIKE :q2 OR `email` LIKE :q3 OR `role` LIKE :q4)';
            $params['q1'] = $term;
            $params['q2'] = $term;
            $params['q3'] = $term;
            $params['q4'] = $term;
        }

        $sql .= ' ORDER BY `is_active` DESC, FIELD(`role`, "admin", "coordinator", "caregiver", "viewer"), `first_name` ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    /**
     * Create a 1-hour password reset token for the given email.
     */
    public function createPasswordReset(string $email): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $now = gmdate('Y-m-d H:i:s');
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 3600);

        $stmt = Database::connection()->prepare(
            'INSERT INTO `password_resets` (`email`, `token_hash`, `expires_at`, `created_at`)
             VALUES (:email, :token_hash, :expires_at, :created_at)'
        );
        $stmt->execute([
            'email' => mb_strtolower(trim($email)),
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

        return $rawToken;
    }

    /**
     * Find a valid, unused password reset record by raw token.
     *
     * @return array<string, mixed>|null
     */
    public function findValidPasswordReset(string $rawToken): ?array
    {
        $tokenHash = hash('sha256', $rawToken);
        $stmt = Database::connection()->prepare(
            'SELECT * FROM `password_resets`
             WHERE `token_hash` = :hash AND `used_at` IS NULL AND `expires_at` > :now
             LIMIT 1'
        );
        $stmt->execute([
            'hash' => $tokenHash,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Mark a password reset token as used.
     */
    public function markPasswordResetUsed(int $id): void
    {
        Database::connection()->prepare(
            'UPDATE `password_resets` SET `used_at` = :now WHERE `id` = :id'
        )->execute([
            'now' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }
}
