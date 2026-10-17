<?php

declare(strict_types=1);

namespace VitaGap\Http;

use PDO;
use Throwable;
use VitaGap\Db\Database;

final class RateLimiter
{
    public const DEFAULT_LIMIT = 60; // 60 requests
    public const WINDOW_SECONDS = 3600; // 1 hour

    public function __construct(
        private readonly int $limit = self::DEFAULT_LIMIT,
        private readonly int $window = self::WINDOW_SECONDS,
        private readonly string $salt = 'vitagap_rate_salt_2026'
    ) {
    }

    /**
     * Check if client IP is within rate limits and increment hit counter.
     */
    public function check(string $ip): bool
    {
        $ipHash = hash('sha256', $ip . $this->salt);
        $windowStart = date('Y-m-d H:00:00');

        // Try MySQL rate limiting first
        try {
            $pdo = Database::getConnection();
            return $this->checkDb($pdo, $ipHash, $windowStart);
        } catch (Throwable) {
            // Fallback to local file-based rate limit store
            return $this->checkFile($ipHash);
        }
    }

    private function checkDb(PDO $pdo, string $ipHash, string $windowStart): bool
    {
        $stmt = $pdo->prepare('SELECT hits FROM rate_limits WHERE ip_hash = :hash AND window_start = :wstart');
        $stmt->execute(['hash' => $ipHash, 'wstart' => $windowStart]);
        $row = $stmt->fetch();

        $hits = $row !== false ? (int) $row['hits'] : 0;
        if ($hits >= $this->limit) {
            return false;
        }

        $upsert = $pdo->prepare(
            'INSERT INTO rate_limits (ip_hash, window_start, hits) 
             VALUES (:hash, :wstart, 1) 
             ON DUPLICATE KEY UPDATE hits = hits + 1'
        );
        $upsert->execute(['hash' => $ipHash, 'wstart' => $windowStart]);

        return true;
    }

    private function checkFile(string $ipHash): bool
    {
        $dir = __DIR__ . '/../../var/rate_limits';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return true; // Gracefully pass if directory creation fails
        }

        $file = $dir . '/' . substr($ipHash, 0, 16) . '.json';
        $now = time();
        $data = ['window_start' => $now, 'hits' => 0];

        if (file_exists($file)) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded) && isset($decoded['window_start'], $decoded['hits'])) {
                    if (($now - (int) $decoded['window_start']) < $this->window) {
                        $data = $decoded;
                    }
                }
            }
        }

        if ($data['hits'] >= $this->limit) {
            return false;
        }

        $data['hits']++;
        file_put_contents($file, json_encode($data), LOCK_EX);

        return true;
    }
}
