<?php

declare(strict_types=1);

namespace VitaGap\Db;

use PDO;
use RuntimeException;
use Throwable;

final class BatchRepository
{
    private readonly string $fileFallbackDir;

    public function __construct(?string $fallbackDir = null)
    {
        $this->fileFallbackDir = $fallbackDir ?? __DIR__ . '/../../var/batches';
    }

    /**
     * Save a batch analysis report.
     *
     * @param array<string, mixed> $batchResult
     * @return array{id: string, share_token: string, delete_token: string}
     */
    public function save(array $batchResult): array
    {
        $id = sprintf('%010x%s', time(), bin2hex(random_bytes(8)));
        $shareToken = bin2hex(random_bytes(16));
        $deleteToken = bin2hex(random_bytes(16));
        $deleteHash = hash('sha256', $deleteToken);
        $name = (string) ($batchResult['name'] ?? 'Microbial Community');
        $createdAt = date('Y-m-d H:i:s.v');

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO batches (id, share_token, delete_hash, name, created_at)
                 VALUES (:id, :token, :dhash, :name, :created)'
            );
            $stmt->execute([
                'id' => $id,
                'token' => $shareToken,
                'dhash' => $deleteHash,
                'name' => $name,
                'created' => $createdAt,
            ]);
        } catch (Throwable) {
            // Check file fallback
        }

        // Always write high-speed JSON representation to disk
        $this->saveToFile($id, $shareToken, $deleteHash, $name, $createdAt, $batchResult);

        return [
            'id' => $id,
            'share_token' => $shareToken,
            'delete_token' => $deleteToken,
        ];
    }

    /**
     * Find a saved batch analysis by share token.
     *
     * @return array<string, mixed>|null
     */
    public function findByShareToken(string $shareToken): ?array
    {
        $filePath = $this->fileFallbackDir . '/' . $shareToken . '.json';
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded) && isset($decoded['result'])) {
                    return $decoded['result'];
                }
            }
        }

        return null;
    }

    private function saveToFile(
        string $id,
        string $shareToken,
        string $deleteHash,
        string $name,
        string $createdAt,
        array $batchResult
    ): void {
        if (!is_dir($this->fileFallbackDir) && !mkdir($this->fileFallbackDir, 0755, true) && !is_dir($this->fileFallbackDir)) {
            throw new RuntimeException("Cannot create batch directory: {$this->fileFallbackDir}");
        }

        $filePath = $this->fileFallbackDir . '/' . $shareToken . '.json';
        $payload = [
            'id' => $id,
            'share_token' => $shareToken,
            'delete_hash' => $deleteHash,
            'name' => $name,
            'created_at' => $createdAt,
            'result' => $batchResult,
        ];

        file_put_contents($filePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}
