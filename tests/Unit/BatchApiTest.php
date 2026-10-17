<?php

declare(strict_types=1);

namespace VitaGap\Tests\Unit;

use VitaGap\Http\JsonResponse;
use VitaGap\Tests\TestCase;

final class BatchApiTest extends TestCase
{
    public function setUp(): void
    {
        JsonResponse::$shouldExit = false;
    }

    public function testBatchApiWorkflow(): void
    {
        $payload = [
            'name' => 'API Test Community',
            'genomes' => [
                [
                    'organism' => 'Microbe 1',
                    'data' => 'RibA -> RibB -> RibD -> RibH -> RibE',
                    'scope' => 'closed',
                    'genome_type' => 'complete',
                    'completeness' => 100.0,
                ],
                [
                    'organism' => 'Microbe 2',
                    'data' => 'RibU',
                    'scope' => 'closed',
                    'genome_type' => 'complete',
                    'completeness' => 100.0,
                ],
            ],
        ];

        // 1. Submit batch via POST /api/batch
        $res = $this->callApi('POST', '/api/batch', $payload);
        $this->assertTrue($res['ok']);
        $this->assertArrayHasKey('share_token', $res['data']);
        $shareToken = $res['data']['share_token'];

        // 2. Retrieve batch via GET /api/batch/{token}
        $getRes = $this->callApi('GET', "/api/batch/{$shareToken}");
        $this->assertTrue($getRes['ok']);
        $this->assertSame('API Test Community', $getRes['data']['result']['name']);
        $this->assertSame(2, $getRes['data']['result']['genome_count']);

        // 3. Export GraphML
        $graphml = $this->callApiRaw('GET', "/api/export/batch/{$shareToken}.graphml");
        $this->assertStringContainsString('<?xml version="1.0"', $graphml);
        $this->assertStringContainsString('<graphml', $graphml);
        $this->assertStringContainsString('Microbe 1', $graphml);

        // 4. Export CSV
        $csv = $this->callApiRaw('GET', "/api/export/batch/{$shareToken}.csv");
        $this->assertStringContainsString('Microbe 1', $csv);
        $this->assertStringContainsString('PROTOTROPH', $csv);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function callApi(string $method, string $uri, array $body = []): array
    {
        $raw = $this->callApiRaw($method, $uri, $body);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("API did not return valid JSON: {$raw}");
        }
        return $decoded;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function callApiRaw(string $method, string $uri, array $body = []): string
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_POST = $body;

        ob_start();
        include __DIR__ . '/../../public/api/index.php';
        return (string) ob_get_clean();
    }
}
