<?php

declare(strict_types=1);

/**
 * Prime Care — Database Migration Runner
 * Usage: php bin/migrate.php [--fresh] [--seed] [--status] [--env=testing]
 */

require_once dirname(__DIR__) . '/app/Helpers/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use Database\Seeds\Phase1Seeder;
use Database\Seeds\Phase2Seeder;
use Database\Seeds\Phase3Seeder;

$args = $argv ?? [];
$isFresh = in_array('--fresh', $args, true);
$doSeed = in_array('--seed', $args, true);
$showStatus = in_array('--status', $args, true);

foreach ($args as $arg) {
    if (str_starts_with($arg, '--env=')) {
        Config::set('app.env', substr($arg, 6));
        Database::disconnect();
    }
}

$pdo = Database::connection();
$migrationsDir = (string) Config::get('paths.migrations', dirname(__DIR__) . '/database/migrations');
$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files);

if ($isFresh) {
    echo "Dropping all existing tables (--fresh)...\n";
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
}

// Ensure migrations tracking table exists
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS `migrations` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `migration` VARCHAR(190) NOT NULL UNIQUE,
        `executed_at` DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$executedStmt = $pdo->query('SELECT `migration`, `executed_at` FROM `migrations` ORDER BY `migration` ASC');
$executedRows = $executedStmt !== false ? $executedStmt->fetchAll() : [];
$executedMap = [];
foreach ($executedRows as $row) {
    $executedMap[(string) $row['migration']] = (string) $row['executed_at'];
}

if ($showStatus) {
    echo "=== Statut des migrations ===\n";
    foreach ($files as $file) {
        $name = basename($file);
        $status = isset($executedMap[$name]) ? "[EXÉCUTÉE le {$executedMap[$name]}]" : '[EN ATTENTE]';
        echo sprintf("  %-36s %s\n", $name, $status);
    }
    exit(0);
}

$ranCount = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($executedMap[$name])) {
        continue;
    }

    echo "Exécution de la migration : {$name}...\n";
    $sql = (string) file_get_contents($file);

    // Strip SQL comments and execute individual statements
    $cleanSql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $statements = array_filter(array_map('trim', explode(';', $cleanSql)));

    foreach ($statements as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }

    $ins = $pdo->prepare('INSERT INTO `migrations` (`migration`, `executed_at`) VALUES (:m, :at)');
    $ins->execute([
        'm' => $name,
        'at' => gmdate('Y-m-d H:i:s'),
    ]);
    $ranCount++;
}

echo $ranCount > 0
    ? "{$ranCount} migration(s) appliquée(s) avec succès.\n"
    : "Aucune nouvelle migration à appliquer.\n";

if ($doSeed) {
    echo "Insertion des données de démonstration (--seed)...\n";
    Phase1Seeder::run();
    Phase2Seeder::run();
    Phase3Seeder::run();
    echo "Seed terminé avec succès.\n";
}
