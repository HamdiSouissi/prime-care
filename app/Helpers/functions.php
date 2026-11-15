<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Lang;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

if (!class_exists('View', false)) {
    class_alias(View::class, 'View');
}

if (!function_exists('e')) {
    /**
     * Escape a value for safe HTML output (XSS protection).
     */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('t')) {
    /**
     * Translate a localization key from lang/fr.php.
     *
     * @param array<string, string|int|float> $replace
     */
    function t(string $key, array $replace = [], ?int $count = null): string
    {
        return Lang::get($key, $replace, $count);
    }
}

if (!function_exists('url')) {
    /**
     * Build an application-relative URL.
     */
    function url(string $path = '/'): string
    {
        $clean = '/' . ltrim($path, '/');
        return $clean !== '/' ? rtrim($clean, '/') : '/';
    }
}

if (!function_exists('route')) {
    /**
     * Generate a URL for a named route.
     *
     * @param array<string, string|int> $params
     */
    function route(string $name, array $params = []): string
    {
        return Router::getInstance()->routeUrl($name, $params);
    }
}

if (!function_exists('asset')) {
    /**
     * Generate a cache-busted asset URL using filemtime.
     */
    function asset(string $path): string
    {
        $clean = ltrim($path, '/');
        if (!str_starts_with($clean, 'assets/')) {
            $clean = 'assets/' . $clean;
        }
        $publicDir = (string) Config::get('paths.public', dirname(__DIR__, 2) . '/public');
        $fullPath = $publicDir . '/' . $clean;
        $version = is_file($fullPath) ? (string) filemtime($fullPath) : '1';
        return '/' . $clean . '?v=' . $version;
    }
}

if (!function_exists('icon')) {
    /**
     * Render an inline Lucide SVG icon from public/assets/vendor/lucide/.
     * Supports both icon('eye', 20) and icon('eye', 'custom-class', 20).
     */
    function icon(string $name, string|int $classOrSize = '', int $size = 20): string
    {
        if (is_int($classOrSize)) {
            $size = $classOrSize;
            $class = '';
        } else {
            $class = $classOrSize;
        }

        $safeName = preg_replace('/[^a-z0-9-]/', '', strtolower($name)) ?: 'info';
        $publicDir = (string) Config::get('paths.public', dirname(__DIR__, 2) . '/public');
        $svgPath = $publicDir . '/assets/vendor/lucide/' . $safeName . '.svg';

        if (!is_file($svgPath)) {
            $svgPath = $publicDir . '/assets/vendor/lucide/info.svg';
        }

        $svg = is_file($svgPath) ? (string) file_get_contents($svgPath) : '';
        if ($svg === '') {
            return '';
        }

        $classes = trim('pc-icon lucide-' . $safeName . ' ' . $class);
        $svg = preg_replace('/\bwidth="[^"]*"/', 'width="' . $size . '"', $svg, 1) ?? $svg;
        $svg = preg_replace('/\bheight="[^"]*"/', 'height="' . $size . '"', $svg, 1) ?? $svg;

        if (str_contains($svg, 'class="')) {
            $svg = preg_replace('/\bclass="[^"]*"/', 'class="' . e($classes) . '" aria-hidden="true"', $svg, 1) ?? $svg;
        } else {
            $svg = preg_replace('/<svg\b/', '<svg class="' . e($classes) . '" aria-hidden="true"', $svg, 1) ?? $svg;
        }

        return $svg;
    }
}

if (!function_exists('brand_svg')) {
    /**
     * Render an inline Prime Care brand SVG with #0A1633 converted to currentColor
     * and unique gradient/title IDs per instance (§6.0 of the brief).
     */
    function brand_svg(string $variant = 'logo-complet', string $class = '', ?int $width = null): string
    {
        $map = [
            'logo-complet' => 'prime-care-logo-complet.svg',
            'prime-care-logo-complet.svg' => 'prime-care-logo-complet.svg',
            'logo-complet-blanc' => 'prime-care-logo-complet-blanc.svg',
            'prime-care-logo-complet-blanc.svg' => 'prime-care-logo-complet-blanc.svg',
            'icone-transparent' => 'prime-care-icone-transparent.svg',
            'prime-care-icone-transparent.svg' => 'prime-care-icone-transparent.svg',
            'icone-degrade' => 'prime-care-icone-degrade.svg',
            'prime-care-icone-degrade.svg' => 'prime-care-icone-degrade.svg',
            'badge' => 'prime-care-badge.svg',
            'prime-care-badge.svg' => 'prime-care-badge.svg',
        ];

        $filename = $map[$variant] ?? 'prime-care-logo-complet.svg';
        $publicDir = (string) Config::get('paths.public', dirname(__DIR__, 2) . '/public');
        $path = $publicDir . '/assets/brand/' . $filename;

        if (!is_file($path)) {
            return '';
        }

        $svg = (string) file_get_contents($path);
        $suffix = substr(bin2hex(random_bytes(4)), 0, 6);

        // Replace #0A1633 with currentColor so logo adapts automatically in dark mode
        $svg = str_replace('#0A1633', 'currentColor', $svg);

        // Make gradient and accessibility IDs unique across the DOM
        foreach (['pcAccent', 'appGradient', 'title', 'desc'] as $idName) {
            $newId = $idName . '_' . $suffix;
            $svg = str_replace('id="' . $idName . '"', 'id="' . $newId . '"', $svg);
            $svg = str_replace('url(#' . $idName . ')', 'url(#' . $newId . ')', $svg);
        }
        $svg = str_replace(
            'aria-labelledby="title desc"',
            'aria-labelledby="title_' . $suffix . ' desc_' . $suffix . '"',
            $svg
        );

        $attrs = 'class="' . e(trim('pc-brand-svg ' . $class)) . '"';
        if ($width !== null) {
            $attrs .= ' width="' . $width . '"';
        }

        $svg = preg_replace('/<svg\b/', '<svg ' . $attrs, $svg, 1) ?? $svg;
        return $svg;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_meta')) {
    function csrf_meta(): string
    {
        return Csrf::meta();
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve previous form input flashed in session.
     */
    function old(string $key, mixed $default = ''): string
    {
        $old = Session::get('_old_input', []);
        if (is_array($old) && array_key_exists($key, $old)) {
            return (string) $old[$key];
        }
        return (string) $default;
    }
}

if (!function_exists('errors')) {
    /**
     * Retrieve validation errors from session.
     */
    function errors(?string $field = null): mixed
    {
        $all = Session::get('_validation_errors', []);
        if (!is_array($all)) {
            return $field === null ? [] : null;
        }
        if ($field === null) {
            return $all;
        }
        $fieldErrors = $all[$field] ?? null;
        if (is_array($fieldErrors)) {
            return $fieldErrors[0] ?? null;
        }
        return is_string($fieldErrors) ? $fieldErrors : null;
    }
}

if (!function_exists('has_error')) {
    /**
     * Check whether a field has a validation error in session.
     */
    function has_error(string $field): bool
    {
        $err = errors($field);
        return is_string($err) && $err !== '';
    }
}

if (!function_exists('field_error')) {
    /**
     * Get the first validation error message for a field.
     */
    function field_error(string $field): ?string
    {
        $err = errors($field);
        return is_string($err) && $err !== '' ? $err : null;
    }
}

if (!function_exists('auth')) {
    /**
     * Get the currently authenticated user record.
     *
     * @return array<string, mixed>|null
     */
    function auth(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('format_date')) {
    /**
     * Format a UTC date/datetime string into Europe/Paris DD/MM/YYYY format.
     */
    function format_date(?string $utcDate, bool|string $withYear = true): string
    {
        if ($utcDate === null || trim($utcDate) === '') {
            return '—';
        }
        try {
            $tzUtc = new DateTimeZone('UTC');
            $tzParis = new DateTimeZone((string) Config::get('app.timezone', 'Europe/Paris'));
            $dt = new DateTimeImmutable($utcDate, $tzUtc);
            if (strlen(trim($utcDate)) > 10) {
                $dt = $dt->setTimezone($tzParis);
            }
            if (is_string($withYear)) {
                return $dt->format($withYear);
            }
            return $dt->format($withYear ? 'd/m/Y' : 'd/m');
        } catch (Throwable) {
            return e($utcDate);
        }
    }
}

if (!function_exists('format_date_fr')) {
    function format_date_fr(?string $utcDate, bool|string $withYear = true): string
    {
        return format_date($utcDate, $withYear);
    }
}

if (!function_exists('format_time')) {
    /**
     * Format a UTC datetime string into Europe/Paris HH:MM format.
     */
    function format_time(?string $utcDateTime): string
    {
        if ($utcDateTime === null || trim($utcDateTime) === '') {
            return '—';
        }
        try {
            $dt = new DateTimeImmutable($utcDateTime, new DateTimeZone('UTC'));
            return $dt->setTimezone(new DateTimeZone((string) Config::get('app.timezone', 'Europe/Paris')))
                ->format('H\hi');
        } catch (Throwable) {
            return e($utcDateTime);
        }
    }
}

if (!function_exists('format_datetime')) {
    /**
     * Format a UTC datetime string into Europe/Paris DD/MM/YYYY à HHhMM.
     */
    function format_datetime(?string $utcDateTime): string
    {
        if ($utcDateTime === null || trim($utcDateTime) === '') {
            return '—';
        }
        return format_date($utcDateTime) . ' à ' . format_time($utcDateTime);
    }
}

if (!function_exists('french_full_date')) {
    /**
     * Format a date in full French words (e.g., "Samedi 14 novembre 2026").
     */
    function french_full_date(?string $utcDate = null): string
    {
        $days = [
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
            7 => 'Dimanche',
        ];
        $months = [
            1 => 'janvier',
            2 => 'février',
            3 => 'mars',
            4 => 'avril',
            5 => 'mai',
            6 => 'juin',
            7 => 'juillet',
            8 => 'août',
            9 => 'septembre',
            10 => 'octobre',
            11 => 'novembre',
            12 => 'décembre',
        ];

        $tz = new DateTimeZone((string) Config::get('app.timezone', 'Europe/Paris'));
        $dt = $utcDate !== null
            ? (new DateTimeImmutable($utcDate, new DateTimeZone('UTC')))->setTimezone($tz)
            : new DateTimeImmutable('now', $tz);

        $dayName = $days[(int) $dt->format('N')] ?? '';
        $dayNum = (int) $dt->format('j');
        $monthName = $months[(int) $dt->format('n')] ?? '';
        $year = $dt->format('Y');

        return sprintf('%s %d %s %s', $dayName, $dayNum, $monthName, $year);
    }
}

if (!function_exists('format_date_long')) {
    function format_date_long(?string $utcDate = null): string
    {
        return french_full_date($utcDate);
    }
}

if (!function_exists('calculate_age')) {
    /**
     * Calculate age in years from a YYYY-MM-DD birth date.
     */
    function calculate_age(string $birthDate): int
    {
        try {
            $tz = new DateTimeZone((string) Config::get('app.timezone', 'Europe/Paris'));
            $dob = new DateTimeImmutable($birthDate, $tz);
            $now = new DateTimeImmutable('now', $tz);
            return $dob->diff($now)->y;
        } catch (Throwable) {
            return 0;
        }
    }
}

if (!function_exists('initials')) {
    /**
     * Extract uppercase initials (1 or 2 characters) from first and last name.
     */
    function initials(string $firstName, string $lastName = ''): string
    {
        $first = trim($firstName);
        $last = trim($lastName);

        if ($last === '' && str_contains($first, ' ')) {
            $parts = preg_split('/\s+/', $first) ?: [];
            $first = $parts[0] ?? '';
            $last = $parts[count($parts) - 1] ?? '';
        }

        $i1 = $first !== '' ? mb_strtoupper(mb_substr($first, 0, 1)) : '';
        $i2 = $last !== '' ? mb_strtoupper(mb_substr($last, 0, 1)) : '';

        return ($i1 . $i2) !== '' ? ($i1 . $i2) : 'PC';
    }
}

if (!function_exists('avatar_color_class')) {
    /**
     * Return a deterministic avatar color class (.pc-avatar--c1 to .pc-avatar--c8) from a user ID.
     */
    function avatar_color_class(int $userId): string
    {
        $index = ((max(1, $userId) - 1) % 8) + 1;
        return 'pc-avatar--c' . $index;
    }
}

if (!function_exists('csp_nonce')) {
    function csp_nonce(): string
    {
        return View::cspNonce();
    }
}

if (!function_exists('can')) {
    /**
     * Check RBAC permission for the currently authenticated user.
     */
    function can(string $resource, string $action): bool
    {
        $user = Auth::user();
        $role = is_array($user) && isset($user['role']) ? (string) $user['role'] : '';
        if ($role === '') {
            return false;
        }
        return \App\Services\Policy::allows($role, $resource, $action);
    }
}

if (!function_exists('quick_add_context')) {
    /**
     * Load shared context for the global '+' quick-add bottom sheets (measure entry, meal entry,
     * as-needed medication, note/handover, quick care).
     *
     * @return array<string, mixed>
     */
    function quick_add_context(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $pdo = \App\Core\Database::connection();
            $tz = new DateTimeZone((string) Config::get('app.timezone', 'Europe/Paris'));
            $nowLocal = new DateTimeImmutable('now', $tz);

            $mtStmt = $pdo->query(
                'SELECT * FROM `measure_types` WHERE `patient_id` = 1 AND `is_active` = 1 AND `deleted_at` IS NULL ORDER BY `sort_order` ASC, `id` ASC'
            );
            $measureTypes = $mtStmt !== false ? $mtStmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $patStmt = $pdo->query('SELECT * FROM `patients` WHERE `id` = 1 LIMIT 1');
            $patient = $patStmt !== false ? ($patStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];

            $contactStmt = $pdo->query(
                'SELECT * FROM `patient_contacts` WHERE `patient_id` = 1 AND `deleted_at` IS NULL ORDER BY `is_emergency_priority` DESC, `sort_order` ASC LIMIT 2'
            );
            $contacts = $contactStmt !== false ? $contactStmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $favStmt = $pdo->query(
                'SELECT * FROM `food_favorites` WHERE `patient_id` = 1 AND `is_active` = 1 AND `deleted_at` IS NULL ORDER BY `sort_order` ASC'
            );
            $favorites = $favStmt !== false ? $favStmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $ciStmt = $pdo->query(
                'SELECT * FROM `care_items` WHERE `patient_id` = 1 AND `is_active` = 1 AND `deleted_at` IS NULL ORDER BY `sort_order` ASC'
            );
            $careItems = $ciStmt !== false ? $ciStmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $anStmt = $pdo->query(
                'SELECT p.*, p.id AS prescription_id, m.name AS medication_name, m.form AS medication_form
                 FROM `prescriptions` p
                 INNER JOIN `medications` m ON m.id = p.medication_id
                 WHERE p.patient_id = 1 AND p.as_needed = 1 AND p.is_active = 1 AND p.deleted_at IS NULL'
            );
            $asNeeded = $anStmt !== false ? $anStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($asNeeded as &$anRow) {
                $anRow['can_give'] = true;
            }
            unset($anRow);

            $handoverSummary = \App\Services\HandoverService::buildShiftSummary();
            $deducedMealType = \App\Services\MealService::deduceMealType($nowLocal->format('H:i'));
            $insulinRotation = \App\Services\InsulinService::getRotationSuggestion(1);

            // Deduce glycemia context from time of day
            $hour = (int) $nowLocal->format('G');
            $deducedGlycemiaContext = match (true) {
                $hour >= 5 && $hour <= 9 => 'fasting',
                ($hour >= 11 && $hour <= 12) || ($hour >= 18 && $hour <= 19) => 'pre_meal',
                ($hour >= 13 && $hour <= 15) || ($hour >= 20 && $hour <= 21) => 'post_meal',
                default => 'bedtime',
            };

            $cached = [
                'measure_types' => $measureTypes,
                'patient' => $patient,
                'priority_contact' => $contacts[0] ?? null,
                'food_favorites' => $favorites,
                'care_items' => $careItems,
                'as_needed_prescriptions' => $asNeeded,
                'as_needed' => $asNeeded,
                'insulin_rotation' => $insulinRotation,
                'handover_summary' => $handoverSummary,
                'now_date' => $nowLocal->format('Y-m-d'),
                'now_time' => $nowLocal->format('H:i'),
                'deduced_meal_type' => $deducedMealType,
                'default_meal_type' => $deducedMealType,
                'deduced_glycemia_context' => $deducedGlycemiaContext,
                'default_glycemia_context' => $deducedGlycemiaContext,
            ];
        } catch (Throwable) {
            $cached = [
                'measure_types' => [],
                'patient' => [],
                'priority_contact' => null,
                'food_favorites' => [],
                'care_items' => [],
                'as_needed_prescriptions' => [],
                'as_needed' => [],
                'insulin_rotation' => [],
                'handover_summary' => [],
                'now_date' => date('Y-m-d'),
                'now_time' => date('H:i'),
                'deduced_meal_type' => 'lunch',
                'default_meal_type' => 'lunch',
                'deduced_glycemia_context' => 'pre_meal',
                'default_glycemia_context' => 'pre_meal',
            ];
        }

        return $cached;
    }
}

