<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validator;

final class ValidatorTest
{
    public static function run(callable $assert): void
    {
        $validData = [
            'email' => 'fatou@primecare.test',
            'password' => 'Soin!2026Abc',
            'password_confirmation' => 'Soin!2026Abc',
            'pin' => '3333',
            'role' => 'caregiver',
        ];

        $v1 = Validator::make($validData, [
            'email' => 'required|email',
            'password' => 'required|min:10|confirmed',
            'pin' => 'required|regex:/^\d{4,6}$/',
            'role' => 'required|in:admin,coordinator,caregiver,viewer',
        ]);

        $assert($v1->passes() === true, 'Validator passes valid payload');
        $assert($v1->errors() === [], 'Validator returns empty errors array on valid payload');

        $invalidData = [
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
            'pin' => '12a',
            'role' => 'hacker',
        ];

        $v2 = Validator::make($invalidData, [
            'email' => 'required|email',
            'password' => 'required|min:10|confirmed',
            'pin' => 'required|regex:/^\d{4,6}$/',
            'role' => 'required|in:admin,coordinator,caregiver,viewer',
        ]);

        $firstErrors = $v2->firstErrors();
        $assert($v2->fails() === true, 'Validator fails invalid payload');
        $assert(isset($firstErrors['email']), 'Validator reports French error message for invalid email');
        $assert(isset($firstErrors['pin']), 'Validator reports error for invalid PIN');
        $assert(isset($firstErrors['role']), 'Validator reports error for disallowed role');
    }
}
