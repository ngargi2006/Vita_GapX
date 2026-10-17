<?php

declare(strict_types=1);

namespace VitaGap\Tests\Unit;

use VitaGap\Db\AnalysisRepository;
use VitaGap\Tests\TestCase;

final class AnalysisRepositoryTest extends TestCase
{
    private AnalysisRepository $repo;
    private string $tempDir;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/vitagap_test_repo_' . bin2hex(random_bytes(4));
        $this->repo = new AnalysisRepository($this->tempDir);
    }

    public function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                foreach ($files as $f) {
                    @unlink($f);
                }
            }
            @rmdir($this->tempDir);
        }
    }

    public function testSaveRetrieveAndDeleteAnalysis(): void
    {
        $dummyReport = [
            'meta' => ['engine_version' => '1.0.0'],
            'input' => ['organism' => 'Test Bacterium', 'sha256' => 'abc123'],
            'vitamins' => ['B2' => ['status' => 'evaluated']],
        ];

        $input = [
            'organism' => 'Test Bacterium',
            'data' => 'RibA -> RibB',
            'store_input' => true,
        ];

        // 1. Save
        $saved = $this->repo->save($dummyReport, $input);
        $this->assertArrayHasKey('share_token', $saved);
        $this->assertArrayHasKey('delete_token', $saved);
        $this->assertSame(32, strlen($saved['share_token']));
        $this->assertSame(32, strlen($saved['delete_token']));

        // 2. Retrieve
        $retrieved = $this->repo->findByShareToken($saved['share_token']);
        $this->assertNotNull($retrieved);
        $this->assertSame('Test Bacterium', $retrieved['input']['organism']);
        $this->assertSame('RibA -> RibB', $retrieved['input']['raw_text']);

        // 3. Delete with wrong token -> fails
        $wrongDelete = $this->repo->delete($saved['share_token'], 'invalid_delete_token_123456789012');
        $this->assertFalse($wrongDelete);

        // 4. Delete with correct token -> succeeds
        $correctDelete = $this->repo->delete($saved['share_token'], $saved['delete_token']);
        $this->assertTrue($correctDelete);

        // 5. Subsequent retrieve returns null
        $this->assertNull($this->repo->findByShareToken($saved['share_token']));
    }
}
