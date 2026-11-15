<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Helpers/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use Database\Seeds\Phase1Seeder;
use Database\Seeds\Phase2Seeder;
use Database\Seeds\Phase3Seeder;
use Database\Seeds\Phase4Seeder;

// C4: Force testing environment so tests NEVER touch the main database
Config::set('app.env', 'testing');
Database::disconnect();

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

/**
 * Prepare the isolated test database (--fresh + migrations 001..010 + Phase1, Phase2 & Phase3 seeders).
 */
function prepareTestDatabase(): void
{
    $host = (string) Config::get('db.host', '127.0.0.1');
    $port = (int) Config::get('db.port', 3306);
    $user = (string) Config::get('db.user', 'root');
    $pass = (string) Config::get('db.pass', '');
    $testDb = preg_replace('/[^a-zA-Z0-9_]/', '', (string) Config::get('db.test_name', 'prime_care_test')) ?: 'prime_care_test';

    $rootPdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$testDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = Database::connection();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tablesStmt = $pdo->query('SHOW TABLES');
    $tables = $tablesStmt !== false ? $tablesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    foreach ($tables as $table) {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
        if ($safeTable !== '') {
            $pdo->exec("DROP TABLE IF EXISTS `{$safeTable}`");
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(190) NOT NULL UNIQUE,
            `executed_at` DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $migrationsDir = (string) Config::get('paths.migrations', dirname(__DIR__) . '/database/migrations');
    $files = glob($migrationsDir . '/*.sql') ?: [];
    sort($files);

    foreach ($files as $file) {
        $name = basename($file);
        $sql = (string) file_get_contents($file);
        $cleanSql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = array_filter(array_map('trim', explode(';', $cleanSql)));
        foreach ($statements as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
        $ins = $pdo->prepare('INSERT INTO `migrations` (`migration`, `executed_at`) VALUES (:m, :at)');
        $ins->execute(['m' => $name, 'at' => gmdate('Y-m-d H:i:s')]);
    }

    Phase1Seeder::run();
    Phase2Seeder::run();
    Phase3Seeder::run();
    Phase4Seeder::run();
}

prepareTestDatabase();

use Tests\Integration\RoutesSmokeTest;
use Tests\Unit\AuthTest;
use Tests\Unit\CsrfTest;
use Tests\Unit\EditWindowHandoverMealTest;
use Tests\Unit\HelperEscapeTest;
use Tests\Unit\IntakeAndInsulinTest;
use Tests\Unit\Phase3Test;
use Tests\Unit\Phase4Test;
use Tests\Unit\PolicyTest;
use Tests\Unit\RateLimiterTest;
use Tests\Unit\RouterTest;
use Tests\Unit\ScheduleServiceTest;
use Tests\Unit\ThresholdServiceTest;
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

$activeDb = (string) Database::connection()->query('SELECT DATABASE()')->fetchColumn();

echo "=====================================================\n";
echo " Prime Care — Suite de tests automatisés (Phases 1, 2, 3 & 4)\n";
echo " Environnement : " . Config::get('app.env') . " (Base : {$activeDb})\n";
echo "=====================================================\n\n";

$assert($activeDb === (string) Config::get('db.test_name', 'prime_care_test'), 'Tests run on isolated test database (' . $activeDb . ')');
echo "\n";

$suites = [
    'Router' => [RouterTest::class, 'run'],
    'Validator' => [ValidatorTest::class, 'run'],
    'Csrf' => [CsrfTest::class, 'run'],
    'Auth & Trusted Devices PIN (C1)' => [AuthTest::class, 'run'],
    'RateLimiter Account vs Shared IP (C2)' => [RateLimiterTest::class, 'run'],
    'Policy (RBAC)' => [PolicyTest::class, 'run'],
    'Helpers & Escaping e()' => [HelperEscapeTest::class, 'run'],
    'ThresholdService (Seuils, Contextes, Plausibilité, g/L <-> mg/dL)' => [ThresholdServiceTest::class, 'run'],
    'ScheduleService (DST Europe/Paris -> UTC & Idempotence 48h)' => [ScheduleServiceTest::class, 'run'],
    'IntakeService & InsulinService (Statuts, Anti-double-prise 409, Grille responsable)' => [IntakeAndInsulinTest::class, 'run'],
    'EditWindowService, MealService, HandoverService & QrCodeService' => [EditWindowHandoverMealTest::class, 'run'],
    'Phase 3 — Planning, Pointage, Heures (DST/Fériés), Alertes & Web Push VAPID' => [Phase3Test::class, 'run'],
    'Phase 4 — Documents, Rapports, Exports CSV, Audit, RGPD, Production & Sécurité' => [Phase4Test::class, 'run'],
    'HTTP Routes & API Smoke Test (D1 + Phase 2 + Phase 3 + Phase 4)' => [RoutesSmokeTest::class, 'run'],
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
