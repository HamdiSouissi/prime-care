<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Policy;

final class PolicyTest
{
    public static function run(callable $assert): void
    {
        // Admin permissions
        $assert(Policy::allows('admin', 'users', 'create') === true, 'Policy allows admin to create users');
        $assert(Policy::allows('admin', 'users', 'view_rate') === true, 'Policy allows admin to view hourly rate');
        $assert(Policy::allows('admin', 'patient', 'update') === true, 'Policy allows admin to update patient');

        // Coordinator permissions
        $assert(Policy::allows('coordinator', 'users', 'create') === false, 'Policy denies coordinator from creating users');
        $assert(Policy::allows('coordinator', 'users', 'view_rate') === false, 'Policy denies coordinator from viewing hourly rate');
        $assert(Policy::allows('coordinator', 'schedule', 'manage') === true, 'Policy allows coordinator to manage schedule');
        $assert(Policy::allows('coordinator', 'patient', 'view') === true, 'Policy allows coordinator to view patient');

        // Caregiver permissions
        $assert(Policy::allows('caregiver', 'vitals', 'create') === true, 'Policy allows caregiver to create vitals');
        $assert(Policy::allows('caregiver', 'patient', 'update') === false, 'Policy denies caregiver from updating patient');
        $assert(Policy::allows('caregiver', 'design', 'view') === false, 'Policy denies caregiver from viewing /design');

        // Viewer permissions
        $assert(Policy::allows('viewer', 'patient', 'view') === true, 'Policy allows viewer to read patient');
        $assert(Policy::allows('viewer', 'vitals', 'create') === false, 'Policy denies viewer from creating vitals');
    }
}
