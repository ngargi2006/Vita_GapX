#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use VitaGap\Db\Database;
use VitaGap\Kb\KbCache;

$jsonPath = __DIR__ . '/../kb/vitagap_kb.json';
$schemaPath = __DIR__ . '/../db/schema.sql';
$cachePath = __DIR__ . '/../var/kb_cache.php';

echo "=== Vita-Gap Knowledge Base Seeder ===\n";

if (!file_exists($jsonPath)) {
    echo "ERROR: KB file not found at {$jsonPath}\n";
    exit(1);
}

$rawJson = file_get_contents($jsonPath);
if ($rawJson === false) {
    echo "ERROR: Unable to read {$jsonPath}\n";
    exit(1);
}

$kb = json_decode($rawJson, true);
if (!is_array($kb)) {
    echo "ERROR: Malformed JSON: " . json_last_error_msg() . "\n";
    exit(1);
}

$sha256 = hash('sha256', $rawJson);
$version = (string) ($kb['kb_version'] ?? '1.0.0');
$keggRelease = (string) ($kb['kegg_release'] ?? 'Release 112.0/Oct 2026');

echo "KB Version: {$version}\n";
echo "KEGG Release: {$keggRelease}\n";
echo "SHA-256: {$sha256}\n";
echo "Vitamins in KB: " . count($kb['vitamins'] ?? []) . "\n";

// 1. Compile OPcache PHP cache file
echo "\n[1/2] Compiling OPcache array cache to {$cachePath}...\n";
KbCache::compile($kb, $cachePath);
echo "✔ Cache compiled successfully!\n";

// 2. Seed Database (if database is reachable)
echo "\n[2/2] Connecting to MySQL database...\n";
try {
    $pdo = Database::getConnection();
    echo "✔ Connected to MySQL successfully!\n";

    // Ensure schema exists
    if (file_exists($schemaPath)) {
        echo "Verifying schema tables...\n";
        $schemaSql = file_get_contents($schemaPath);
        if ($schemaSql !== false) {
            $pdo->exec($schemaSql);
            echo "✔ Schema tables ensured.\n";
        }
    }

    $pdo->beginTransaction();

    // Check or insert kb_versions
    $stmt = $pdo->prepare('SELECT id FROM kb_versions WHERE version = :version LIMIT 1');
    $stmt->execute(['version' => $version]);
    $kbVersionRow = $stmt->fetch();

    if ($kbVersionRow) {
        $kbVersionId = (int) $kbVersionRow['id'];
        $updateStmt = $pdo->prepare('UPDATE kb_versions SET kegg_release = :rel, sha256 = :sha, is_active = 1 WHERE id = :id');
        $updateStmt->execute(['rel' => $keggRelease, 'sha' => $sha256, 'id' => $kbVersionId]);
    } else {
        $insertStmt = $pdo->prepare('INSERT INTO kb_versions (version, kegg_release, sha256, is_active) VALUES (:ver, :rel, :sha, 1)');
        $insertStmt->execute(['ver' => $version, 'rel' => $keggRelease, 'sha' => $sha256]);
        $kbVersionId = (int) $pdo->lastInsertId();
    }

    // Set other versions inactive
    $pdo->prepare('UPDATE kb_versions SET is_active = 0 WHERE id != :id')->execute(['id' => $kbVersionId]);

    // Insert vitamins, pathways, steps
    foreach ($kb['vitamins'] ?? [] as $vit) {
        $vitCode = strtoupper($vit['code']);
        $vitStmt = $pdo->prepare('INSERT INTO vitamins (code, name, active_forms, summary) 
            VALUES (:code, :name, :active_forms, :summary)
            ON DUPLICATE KEY UPDATE name = VALUES(name), active_forms = VALUES(active_forms), summary = VALUES(summary)');
        $vitStmt->execute([
            'code' => $vitCode,
            'name' => $vit['name'],
            'active_forms' => $vit['active_forms'] ?? null,
            'summary' => $vit['summary'] ?? null,
        ]);

        $vIdStmt = $pdo->prepare('SELECT id FROM vitamins WHERE code = :code LIMIT 1');
        $vIdStmt->execute(['code' => $vitCode]);
        $vitaminId = (int) $vIdStmt->fetchColumn();

        foreach ($vit['pathways'] ?? [] as $pw) {
            $pwCode = strtoupper($pw['code']);
            $pwStmt = $pdo->prepare('INSERT INTO pathways (kb_version_id, vitamin_id, code, name, role, kegg_modules, loci_k)
                VALUES (:kb_ver, :vit_id, :code, :name, :role, :modules, :loci)
                ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role), kegg_modules = VALUES(kegg_modules), loci_k = VALUES(loci_k)');
            $pwStmt->execute([
                'kb_ver' => $kbVersionId,
                'vit_id' => $vitaminId,
                'code' => $pwCode,
                'name' => $pw['name'],
                'role' => $pw['role'],
                'modules' => isset($pw['kegg_modules']) ? implode(',', $pw['kegg_modules']) : null,
                'loci' => $pw['loci_k'] ?? 1,
            ]);

            $pIdStmt = $pdo->prepare('SELECT id FROM pathways WHERE kb_version_id = :kb_ver AND code = :code LIMIT 1');
            $pIdStmt->execute(['kb_ver' => $kbVersionId, 'code' => $pwCode]);
            $pathwayId = (int) $pIdStmt->fetchColumn();

            // Clear old steps for this pathway (clean refresh)
            $pdo->prepare('DELETE FROM steps WHERE pathway_id = :pid')->execute(['pid' => $pathwayId]);

            $pos = 1;
            foreach ($pw['steps'] ?? [] as $st) {
                $stepStmt = $pdo->prepare('INSERT INTO steps (pathway_id, code, pos, branch, name, ec, kind, note)
                    VALUES (:pid, :code, :pos, :branch, :name, :ec, :kind, :note)');
                $stepStmt->execute([
                    'pid' => $pathwayId,
                    'code' => $st['code'],
                    'pos' => $pos++,
                    'branch' => $st['branch'] ?? null,
                    'name' => $st['name'],
                    'ec' => $st['ec'] ?? null,
                    'kind' => $st['kind'] ?? 'normal',
                    'note' => $st['note'] ?? null,
                ]);
                $stepId = (int) $pdo->lastInsertId();

                foreach ($st['options'] ?? [] as $opt) {
                    $optStmt = $pdo->prepare('INSERT INTO step_options (step_id, kos, label) VALUES (:sid, :kos, :label)');
                    $optStmt->execute([
                        'sid' => $stepId,
                        'kos' => $opt,
                        'label' => $opt,
                    ]);
                }
            }
        }

        // Aliases
        foreach ($vit['aliases'] ?? [] as $al) {
            $aliasStmt = $pdo->prepare('INSERT INTO gene_aliases (alias, ko, naming, source) 
                VALUES (:alias, :ko, :naming, :src)');
            $aliasStmt->execute([
                'alias' => strtolower(trim($al['alias'])),
                'ko' => strtoupper(trim($al['ko'])),
                'naming' => $al['naming'] ?? 'generic',
                'src' => 'vitagap_kb_seed',
            ]);
        }
    }

    $pdo->commit();
    echo "✔ Database seeded idempotently with version {$version}!\n";
} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "⚠ Database notice: " . $e->getMessage() . "\n";
    echo "  (Vita-Gap is designed to run seamlessly from high-speed cache file var/kb_cache.php when MySQL is offline)\n";
}

echo "\nDone!\n";
exit(0);
