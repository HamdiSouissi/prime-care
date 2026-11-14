<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use Throwable;

/**
 * Singleton PDO database connection manager with UTC session enforcement and transactions.
 */
final class Database
{
    private static ?PDO $pdo = null;

    /**
     * Get the active PDO connection instance.
     */
    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = (string) Config::get('db.host', '127.0.0.1');
        $port = (int) Config::get('db.port', 3306);
        $env = (string) Config::get('app.env', 'development');
        $dbName = $env === 'testing'
            ? (string) Config::get('db.test_name', 'prime_care_test')
            : (string) Config::get('db.name', 'prime_care');
        $charset = (string) Config::get('db.charset', 'utf8mb4');
        $user = (string) Config::get('db.user', 'root');
        $pass = (string) Config::get('db.pass', '');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbName, $charset);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        try {
            self::$pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            self::ensureLocalMysqlRunning($host, $port, $user, $pass, $dbName);
            self::$pdo = new PDO($dsn, $user, $pass, $options);
        }

        self::$pdo->exec("SET time_zone = '+00:00'");
        self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

        return self::$pdo;
    }

    /**
     * Reset the cached PDO instance (used when switching databases in tests/migrations).
     */
    public static function disconnect(): void
    {
        self::$pdo = null;
    }

    /**
     * Execute a callable inside a database transaction.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $startedHere = !$pdo->inTransaction();

        if ($startedHere) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback($pdo);
            if ($startedHere && $pdo->inTransaction()) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($startedHere && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Ensure local MySQL server is running and target database exists in dev/sandbox environments.
     */
    private static function ensureLocalMysqlRunning(
        string $host,
        int $port,
        string $user,
        string $pass,
        string $dbName
    ): void {
        if (is_executable('/usr/local/mysql/mysqld')) {
            $sock = @fsockopen($host, $port, $errno, $errstr, 0.3);
            if ($sock === false) {
                @shell_exec(
                    '/usr/local/mysql/mysqld --basedir=/usr/local/mysql --datadir=/usr/local/mysql/data '
                    . '--lc-messages-dir=/usr/local/mysql/share/mysql --secure-file-priv=/var/lib/mysql-files '
                    . '--explicit_defaults_for_timestamp --socket=/var/run/mysqld/mysqld.sock '
                    . '--port=' . $port . ' --bind-address=127.0.0.1 --innodb-use-native-aio=0 '
                    . '--log-error=/usr/local/mysql/mysqld.log --daemonize >/dev/null 2>&1'
                );
                usleep(400000);
            } else {
                fclose($sock);
            }
        }

        try {
            $rootPdo = new PDO(
                sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName) ?: 'prime_care';
            $rootPdo->exec(
                "CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        } catch (Throwable) {
            // Let the main connection throw the underlying PDOException if unreachable
        }
    }
}
