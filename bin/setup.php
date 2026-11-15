<?php

declare(strict_types=1);

/**
 * Prime Care — Assistant d'installation en ligne de commande (Phase 4 §6.2)
 *
 * Usage :
 *   php bin/setup.php                           (mode interactif)
 *   php bin/setup.php --config=setup.ini        (mode automatisé via fichier INI)
 *   php bin/setup.php --config=setup.ini --force (réinstallation forcée)
 */

$rootDir = dirname(__DIR__);
require_once $rootDir . '/app/Core/Auth.php';

$options = getopt('', ['config::', 'force', 'output-config::', 'skip-db']);
$force = array_key_exists('force', $options);
$configIniPath = isset($options['config']) && is_string($options['config']) ? $options['config'] : null;
$targetConfigFile = isset($options['output-config']) && is_string($options['output-config'])
    ? $options['output-config']
    : ($rootDir . '/config/config.php');

echo "========================================================\n";
echo "  Prime Care v1.0.0 — Assistant d'installation\n";
echo "========================================================\n\n";

// 1. Vérification PHP >= 8.2 & extensions requises
if (PHP_VERSION_ID < 80200) {
    fwrite(STDERR, "[ERREUR] PHP 8.2 ou supérieur est requis (version actuelle : " . PHP_VERSION . ").\n");
    exit(1);
}
echo "[OK] Version PHP : " . PHP_VERSION . "\n";

$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'gd', 'openssl', 'fileinfo', 'json'];
$missingExt = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExt[] = $ext;
    }
}
if ($missingExt !== []) {
    fwrite(STDERR, "[ERREUR] Extensions PHP manquantes : " . implode(', ', $missingExt) . "\n");
    exit(1);
}
echo "[OK] Extensions PHP requises : " . implode(', ', $requiredExtensions) . "\n";

// 2. Vérification des répertoires et permissions de storage/
$storageDirs = [
    $rootDir . '/storage',
    $rootDir . '/storage/uploads',
    $rootDir . '/storage/uploads/documents',
    $rootDir . '/storage/logs',
    $rootDir . '/storage/cache',
    $rootDir . '/storage/backups',
];
foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!is_writable($dir)) {
        fwrite(STDERR, "[ERREUR] Le répertoire n'est pas accessible en écriture : {$dir}\n");
        exit(1);
    }
}
echo "[OK] Permissions d'écriture sur storage/ vérifiées.\n";

// 3. Vérification si déjà installé (fichier config/config.php existant)
if (is_file($targetConfigFile) && !$force) {
    fwrite(STDERR, "[REFUS] L'application est déjà installée ({$targetConfigFile} existe). Utilisez --force pour forcer la réinstallation.\n");
    exit(1);
}

// 4. Lecture des paramètres depuis --config=fichier.ini ou en mode interactif
$ini = [];
if ($configIniPath !== null) {
    if (!is_file($configIniPath)) {
        fwrite(STDERR, "[ERREUR] Fichier de configuration INI introuvable : {$configIniPath}\n");
        exit(1);
    }
    $parsed = parse_ini_file($configIniPath, true);
    if (!is_array($parsed)) {
        fwrite(STDERR, "[ERREUR] Impossible de parser le fichier INI : {$configIniPath}\n");
        exit(1);
    }
    $ini = $parsed;
}

$ask = static function (string $prompt, string $default = '') use ($configIniPath): string {
    if ($configIniPath !== null) {
        return $default;
    }
    echo $prompt . ($default !== '' ? " [{$default}]" : '') . ' : ';
    $line = fgets(STDIN);
    $val = $line !== false ? trim($line) : '';
    return $val !== '' ? $val : $default;
};

$dbHost = (string) ($ini['database']['host'] ?? $ask('Hôte MySQL', '127.0.0.1'));
$dbPort = (int) ($ini['database']['port'] ?? $ask('Port MySQL', '3306'));
$dbName = (string) ($ini['database']['name'] ?? $ask('Nom de la base MySQL', 'prime_care'));
$dbUser = (string) ($ini['database']['user'] ?? $ask('Utilisateur MySQL', 'root'));
$dbPass = (string) ($ini['database']['pass'] ?? $ask('Mot de passe MySQL', ''));

$appUrl = (string) ($ini['app']['url'] ?? $ask('URL publique de l’application', 'https://prime-care.example.fr'));
$appEnv = (string) ($ini['app']['env'] ?? $ask('Environnement (production/development)', 'production'));
$smtpHost = (string) ($ini['mail']['host'] ?? $ask('Serveur SMTP', 'smtp.example.fr'));
$smtpFrom = (string) ($ini['mail']['from_address'] ?? $ask('Adresse e-mail d’expédition', 'alertes@prime-care.example.fr'));

$adminFirst = (string) ($ini['admin']['first_name'] ?? $ask('Prénom du premier administrateur', 'Karim'));
$adminLast = (string) ($ini['admin']['last_name'] ?? $ask('Nom du premier administrateur', 'Benali'));
$adminEmail = (string) ($ini['admin']['email'] ?? $ask('E-mail du premier administrateur', 'karim@primecare.test'));
$adminPass = (string) ($ini['admin']['password'] ?? $ask('Mot de passe administrateur (min. 10 car.)', 'PrimeCare!2026'));

$patientFirst = (string) ($ini['patient']['first_name'] ?? $ask('Prénom du patient suivi', 'Henri'));
$patientLast = (string) ($ini['patient']['last_name'] ?? $ask('Nom du patient suivi', 'Benali'));
$patientBirth = (string) ($ini['patient']['birth_date'] ?? $ask('Date de naissance du patient (AAAA-MM-JJ)', '1942-03-15'));
$patientDiabetes = (string) ($ini['patient']['diabetes_type'] ?? $ask('Type de diabète / pathologie', 'Diabète de type 2 insulinoréquérant'));

// 5. Connexion MySQL & vérification d'installation existante en base
if (!array_key_exists('skip-db', $options)) {
    try {
        $pdoServer = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort),
            $dbUser,
            $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName),
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        // Vérifier si la base contient déjà un administrateur actif (sauf si --force)
        if (!$force) {
            $hasUsersTable = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
            if ($hasUsersTable !== false) {
                $existingCount = (int) $pdo->query('SELECT COUNT(*) FROM `users`')->fetchColumn();
                if ($existingCount > 0) {
                    fwrite(STDERR, "[REFUS] La base de données `{$dbName}` contient déjà {$existingCount} utilisateur(s). Utilisez --force pour continuer.\n");
                    exit(1);
                }
            }
        }

        // Exécution des migrations 001 à 011
        $migrationsDir = $rootDir . '/database/migrations';
        $files = glob($migrationsDir . '/*.sql') ?: [];
        sort($files);
        foreach ($files as $sqlFile) {
            $sql = file_get_contents($sqlFile);
            if ($sql !== false && trim($sql) !== '') {
                $pdo->exec($sql);
                echo "[MIGRATION] " . basename($sqlFile) . " appliquée.\n";
            }
        }

        // Création / mise à jour du premier patient et du premier compte admin
        $nowUtc = gmdate('Y-m-d H:i:s');
        $patExists = (int) $pdo->query('SELECT COUNT(*) FROM `patients` WHERE `id` = 1')->fetchColumn();
        if ($patExists === 0) {
            $stmtPat = $pdo->prepare(
                'INSERT INTO `patients`
                 (`id`, `first_name`, `last_name`, `birth_date`, `diabetes_type`,
                  `target_glycemia_fasting_min`, `target_glycemia_fasting_max`, `target_glycemia_postprandial_max`,
                  `threshold_hypo_critical`, `threshold_hypo`, `threshold_hyper`, `threshold_hyper_critical`,
                  `emergency_protocol`, `created_at`, `updated_at`)
                 VALUES (1, :fn, :ln, :bd, :dt, 0.80, 1.30, 1.80, 0.54, 0.70, 2.50, 3.00, :proto, :c, :u)'
            );
            $stmtPat->execute([
                'fn' => $patientFirst,
                'ln' => $patientLast,
                'bd' => $patientBirth,
                'dt' => $patientDiabetes,
                'proto' => 'Resucrage immédiat (15 g de glucides rapides) si glycémie < 0,70 g/L, contrôle à +15 min. Appel SAMU (15) si malaise ou glycémie < 0,54 g/L.',
                'c' => $nowUtc,
                'u' => $nowUtc,
            ]);
            echo "[OK] Premier patient créé : {$patientFirst} {$patientLast}\n";
        }

        $admStmt = $pdo->prepare('SELECT `id` FROM `users` WHERE LOWER(`email`) = LOWER(:em) LIMIT 1');
        $admStmt->execute(['em' => $adminEmail]);
        $admId = $admStmt->fetchColumn();
        $passHash = password_hash($adminPass, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);

        if ($admId === false) {
            $insAdm = $pdo->prepare(
                'INSERT INTO `users`
                 (`patient_id`, `first_name`, `last_name`, `email`, `password_hash`, `role`, `is_active`, `theme`, `created_at`, `updated_at`)
                 VALUES (1, :fn, :ln, :em, :ph, \'admin\', 1, \'auto\', :c, :u)'
            );
            $insAdm->execute([
                'fn' => $adminFirst,
                'ln' => $adminLast,
                'em' => $adminEmail,
                'ph' => $passHash,
                'c' => $nowUtc,
                'u' => $nowUtc,
            ]);
            echo "[OK] Premier compte administrateur créé : {$adminEmail}\n";
        } else {
            echo "[OK] Compte administrateur existant vérifié : {$adminEmail}\n";
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "[ERREUR MYSQL] " . $e->getMessage() . "\n");
        exit(1);
    }
}

// 6. Génération du fichier de configuration avec secrets aléatoires
$appSecret = bin2hex(random_bytes(32));
$qrHmacSecret = bin2hex(random_bytes(32));
$isProd = ($appEnv === 'production');

$configPhp = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export([
    'app' => [
        'name' => 'Prime Care',
        'slogan' => 'Suivi de soins à domicile',
        'publisher' => 'Prime Vision Solutions',
        'publisher_url' => 'https://primevisionsolutions.fr',
        'version' => '1.0.0',
        'url' => $appUrl,
        'env' => $appEnv,
        'debug' => !$isProd,
        'timezone' => 'Europe/Paris',
        'db_timezone' => 'UTC',
        'locale' => 'fr',
        'loader_min_ms' => 2000,
        'secret_key' => $appSecret,
        'qr_hmac_secret' => $qrHmacSecret,
    ],
    'db' => [
        'driver' => 'mysql',
        'host' => $dbHost,
        'port' => $dbPort,
        'name' => $dbName,
        'test_name' => $dbName . '_test',
        'user' => $dbUser,
        'pass' => $dbPass,
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],
    'session' => [
        'name' => 'prime_care_session',
        'lifetime' => 7200,
        'idle_timeout' => 1800,
        'secure' => $isProd,
        'httponly' => true,
        'samesite' => 'Lax',
    ],
    'mail' => [
        'driver' => $isProd ? 'smtp' : 'log',
        'host' => $smtpHost,
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls',
        'from_address' => $smtpFrom,
        'from_name' => 'Prime Care — Suivi de soins',
    ],
    'security' => [
        'login_max_attempts' => 5,
        'ip_max_attempts' => 30,
        'login_lockout_minutes' => 15,
        'remember_days' => 30,
        'trusted_device_days' => 90,
        'password_reset_minutes' => 60,
        'password_min_length' => 10,
        'pin_min_length' => 4,
        'pin_max_length' => 6,
        'max_upload_bytes' => 10 * 1024 * 1024,
        'api_write_limit_per_min' => 120,
    ],
    'paths' => [
        'root' => $rootDir,
        'app' => $rootDir . '/app',
        'public' => $rootDir . '/public',
        'storage' => $rootDir . '/storage',
        'uploads' => $rootDir . '/storage/uploads',
        'logs' => $rootDir . '/storage/logs',
        'cache' => $rootDir . '/storage/cache',
        'views' => $rootDir . '/app/Views',
        'lang' => $rootDir . '/lang',
        'migrations' => $rootDir . '/database/migrations',
        'seeds' => $rootDir . '/database/seeds',
    ],
], true) . ";\n";

file_put_contents($targetConfigFile, $configPhp);
@chmod($targetConfigFile, 0640);

echo "[OK] Configuration générée avec secrets aléatoires : {$targetConfigFile}\n";
echo "\nInstallation de Prime Care v1.0.0 terminée avec succès.\n";
exit(0);
