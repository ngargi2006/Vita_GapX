<?php

declare(strict_types=1);

namespace VitaGap\Db;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    /**
     * Get or initialize PDO connection.
     */
    public static function getConnection(?array $overrideConfig = null): PDO
    {
        if (self::$pdo !== null && $overrideConfig === null) {
            return self::$pdo;
        }

        self::loadEnvFile(__DIR__ . '/../../.env');

        $host = $overrideConfig['host'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $port = (int) ($overrideConfig['port'] ?? getenv('DB_PORT') ?: 3306);
        $dbname = $overrideConfig['dbname'] ?? getenv('DB_NAME') ?: 'vitagap';
        $user = $overrideConfig['user'] ?? getenv('DB_USER') ?: 'vitagap_user';
        $pass = $overrideConfig['pass'] ?? getenv('DB_PASS') ?: 'vitagap_secret';
        $charset = $overrideConfig['charset'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbname, $charset);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            $pdo->exec("SET NAMES {$charset} COLLATE utf8mb4_unicode_ci");
            if ($overrideConfig === null) {
                self::$pdo = $pdo;
            }
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Minimal .env file parser if getenv is unset.
     */
    public static function loadEnvFile(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (!str_contains($trimmed, '=')) {
                continue;
            }

            [$key, $val] = explode('=', $trimmed, 2);
            $key = trim($key);
            $val = trim($val);

            // Strip quotes if present
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }

            if (getenv($key) === false) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
            }
        }
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
