<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Helpers/bootstrap.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Tests\\';
    $baseDir = __DIR__ . '/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

use Tests\Unit\AuthTest;
use Tests\Unit\CsrfTest;
use Tests\Unit\HelperEscapeTest;
use Tests\Unit\PolicyTest;
use Tests\Unit\RateLimiterTest;
use Tests\Unit\RouterTest;
use Tests\Unit\ValidatorTest;

$passed = 0;
$failed = 0;

$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "  ✓ {$label}\n";
    } else {
        $failed++;
        echo "  ✗ FAIL: {$label}\n";
    }
};

echo "=====================================================\n";
echo " Prime Care — Suite de tests automatisés (Phase 1)\n";
echo "=====================================================\n\n";

$suites = [
    'Router' => [RouterTest::class, 'run'],
    'Validator' => [ValidatorTest::class, 'run'],
    'Csrf' => [CsrfTest::class, 'run'],
    'Auth (Password & PIN)' => [AuthTest::class, 'run'],
    'RateLimiter' => [RateLimiterTest::class, 'run'],
    'Policy (RBAC)' => [PolicyTest::class, 'run'],
    'Helpers & Escaping e()' => [HelperEscapeTest::class, 'run'],
];

foreach ($suites as $name => $callable) {
    echo "▸ {$name}\n";
    $callable($assert);
    echo "\n";
}

echo "-----------------------------------------------------\n";
echo " Résultat : {$passed} réussis, {$failed} échoués\n";
echo "-----------------------------------------------------\n";

exit($failed > 0 ? 1 : 0);
