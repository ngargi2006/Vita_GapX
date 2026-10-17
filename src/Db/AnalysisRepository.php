<?php

declare(strict_types=1);

namespace VitaGap\Db;

use PDO;
use RuntimeException;
use Throwable;

final class AnalysisRepository
{
    private readonly string $fileFallbackDir;

    public function __construct(?string $fallbackDir = null)
    {
        $this->fileFallbackDir = $fallbackDir ?? __DIR__ . '/../../var/analyses';
    }

    /**
     * Save an analysis report and generate share and delete tokens.
     *
     * @param array<string, mixed> $report Full analysis report structure
     * @param array<string, mixed> $input Validated user input
     * @return array{id: string, share_token: string, delete_token: string}
     */
    public function save(array $report, array $input): array
    {
        $id = $this->generateId();
        $shareToken = bin2hex(random_bytes(16));
        $deleteToken = bin2hex(random_bytes(16));
        $deleteHash = hash('sha256', $deleteToken);
        $createdAt = date('Y-m-d H:i:s.v');

        $organism = $input['organism'] ?? '';
        $genomeType = $input['genome_type'] ?? 'unknown';
        $completeness = $input['completeness'] ?? null;
        $scope = $input['scope'] ?? 'open';
        $format = $input['format'] ?? 'auto';
        $sha256 = $report['input']['sha256'] ?? hash('sha256', (string) ($input['data'] ?? ''));
        $inputText = !empty($input['store_input']) ? (string) ($input['data'] ?? '') : null;
        $engineVersion = $report['meta']['engine_version'] ?? '1.0.0';

        // Try persisting into MySQL
        try {
            $pdo = Database::getConnection();
            $this->saveToDb(
                $pdo,
                $id,
                $shareToken,
                $deleteHash,
                $createdAt,
                $engineVersion,
                $organism,
                $genomeType,
                $completeness,
                $scope,
                $format,
                $sha256,
                $inputText,
                $input,
                $report
            );
        } catch (Throwable) {
            // Fallback to high-speed file storage
            $this->saveToFile($id, $shareToken, $deleteHash, $createdAt, $report, $inputText, $input);
        }

        return [
            'id' => $id,
            'share_token' => $shareToken,
            'delete_token' => $deleteToken,
        ];
    }

    /**
     * Retrieve a saved analysis report by its share token.
     *
     * @return array<string, mixed>|null
     */
    public function findByShareToken(string $shareToken): ?array
    {
        // Try MySQL first
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT report, input_text FROM analyses WHERE share_token = :token');
            $stmt->execute(['token' => $shareToken]);
            $row = $stmt->fetch();
            if ($row !== false && isset($row['report'])) {
                $report = json_decode((string) $row['report'], true);
                if (is_array($report)) {
                    if ($row['input_text'] !== null) {
                        $report['input']['raw_text'] = $row['input_text'];
                    }
                    return $report;
                }
            }
        } catch (Throwable) {
            // Check file fallback
        }

        return $this->findInFile($shareToken);
    }

    /**
     * Delete an analysis using its share token and delete token.
     */
    public function delete(string $shareToken, string $deleteToken): bool
    {
        $targetHash = hash('sha256', $deleteToken);

        // Try MySQL
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT delete_hash FROM analyses WHERE share_token = :token');
            $stmt->execute(['token' => $shareToken]);
            $row = $stmt->fetch();
            if ($row !== false) {
                if (hash_equals((string) $row['delete_hash'], $targetHash)) {
                    $del = $pdo->prepare('DELETE FROM analyses WHERE share_token = :token');
                    $del->execute(['token' => $shareToken]);
                    return true;
                }
                return false;
            }
        } catch (Throwable) {
            // File fallback
        }

        return $this->deleteFromFile($shareToken, $targetHash);
    }

    private function saveToDb(
        PDO $pdo,
        string $id,
        string $shareToken,
        string $deleteHash,
        string $createdAt,
        string $engineVersion,
        string $organism,
        string $genomeType,
        ?float $completeness,
        string $scope,
        string $format,
        string $sha256,
        ?string $inputText,
        array $input,
        array $report
    ): void {
        $stmtKb = $pdo->query('SELECT id FROM kb_versions ORDER BY id DESC LIMIT 1');
        $kbRow = $stmtKb !== false ? $stmtKb->fetch() : null;
        $kbVersionId = $kbRow ? (int) $kbRow['id'] : 1;

        $stmt = $pdo->prepare(
            'INSERT INTO analyses (
                id, share_token, delete_hash, created_at, kb_version_id, engine_version,
                organism, genome_type, completeness, input_scope, input_format,
                input_sha256, input_text, params, report
            ) VALUES (
                :id, :stoken, :dhash, :created, :kbid, :engver,
                :org, :gtype, :comp, :scope, :fmt,
                :sha, :inptext, :params, :report
            )'
        );

        $stmt->execute([
            'id' => $id,
            'stoken' => $shareToken,
            'dhash' => $deleteHash,
            'created' => $createdAt,
            'kbid' => $kbVersionId,
            'engver' => $engineVersion,
            'org' => $organism,
            'gtype' => $genomeType,
            'comp' => $completeness,
            'scope' => $scope,
            'fmt' => $format,
            'sha' => $sha256,
            'inptext' => $inputText,
            'params' => json_encode($input, JSON_UNESCAPED_SLASHES),
            'report' => json_encode($report, JSON_UNESCAPED_SLASHES),
        ]);

        // Insert results summary rows
        $stmtRes = $pdo->prepare(
            'INSERT INTO analysis_results (
                analysis_id, vitamin_code, best_pathway, completeness, key_coverage, call_code, evidence
            ) VALUES (
                :aid, :vcode, :pw, :comp, :kc, :call, :evi
            )'
        );

        foreach ($report['vitamins'] ?? [] as $vCode => $vData) {
            $stmtRes->execute([
                'aid' => $id,
                'vcode' => $vCode,
                'pw' => $vData['best_pathway']['code'] ?? '',
                'comp' => ($vData['q3_completeness']['raw']['weighted'] ?? 0.0),
                'kc' => ($vData['q3_completeness']['raw']['key_coverage'] ?? 0.0),
                'call' => $vData['q4_call']['code'] ?? '',
                'evi' => (int) ($vData['q5_evidence']['score'] ?? 0),
            ]);
        }
    }

    private function saveToFile(
        string $id,
        string $shareToken,
        string $deleteHash,
        string $createdAt,
        array $report,
        ?string $inputText,
        array $input
    ): void {
        if (!is_dir($this->fileFallbackDir) && !mkdir($this->fileFallbackDir, 0755, true) && !is_dir($this->fileFallbackDir)) {
            throw new RuntimeException("Cannot create storage directory: {$this->fileFallbackDir}");
        }

        $filePath = $this->fileFallbackDir . '/' . $shareToken . '.json';
        $payload = [
            'id' => $id,
            'share_token' => $shareToken,
            'delete_hash' => $deleteHash,
            'created_at' => $createdAt,
            'report' => $report,
            'input_text' => $inputText,
            'input' => $input,
        ];

        file_put_contents($filePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function findInFile(string $shareToken): ?array
    {
        $filePath = $this->fileFallbackDir . '/' . $shareToken . '.json';
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['report'])) {
            return null;
        }

        $report = $data['report'];
        if (isset($data['input_text']) && $data['input_text'] !== null) {
            $report['input']['raw_text'] = $data['input_text'];
        }

        return $report;
    }

    private function deleteFromFile(string $shareToken, string $targetHash): bool
    {
        $filePath = $this->fileFallbackDir . '/' . $shareToken . '.json';
        if (!file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['delete_hash'])) {
            return false;
        }

        if (hash_equals((string) $data['delete_hash'], $targetHash)) {
            unlink($filePath);
            return true;
        }

        return false;
    }

    private function generateId(): string
    {
        // 26-char monotonic timestamp + random hex
        return sprintf('%010x%s', time(), bin2hex(random_bytes(8)));
    }
}
