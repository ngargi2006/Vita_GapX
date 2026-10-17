<?php

declare(strict_types=1);

namespace VitaGap\Tests\Unit;

use VitaGap\Http\JsonResponse;
use VitaGap\Tests\TestCase;

final class ApiEndpointsTest extends TestCase
{
    public function setUp(): void
    {
        JsonResponse::$shouldExit = false;
    }

    public function testGetVitaminsEndpoint(): void
    {
        $response = $this->callApi('GET', '/api/vitamins');
        $this->assertTrue($response['ok']);
        $this->assertArrayHasKey('vitamins', $response['data']);
        $this->assertCount(9, $response['data']['vitamins']);
    }

    public function testGetPathwayEndpoint(): void
    {
        $response = $this->callApi('GET', '/api/pathways/B2_DENOVO');
        $this->assertTrue($response['ok']);
        $this->assertSame('B2', $response['data']['vitamin_code']);
        $this->assertSame('B2_DENOVO', $response['data']['pathway']['code']);
    }

    public function testLookupEndpoint(): void
    {
        $_GET['q'] = 'ribC';
        $_GET['naming'] = 'generic';

        $response = $this->callApi('GET', '/api/lookup?q=ribC&naming=generic');
        $this->assertTrue($response['ok']);
        $this->assertCount(1, $response['data']['ambiguities']);
        $this->assertSame('ribc', $response['data']['ambiguities'][0]['symbol']);

        unset($_GET['q'], $_GET['naming']);
    }

    public function testParseEndpoint(): void
    {
        $payload = [
            'data' => "RibA -> RibB -> RibD",
            'naming' => 'generic',
        ];

        $response = $this->callApi('POST', '/api/parse', $payload);
        $this->assertTrue($response['ok']);
        $this->assertSame('plain_list', $response['data']['detected_format']);
        $this->assertSame(3, $response['data']['total_tokens']);
    }

    public function testAnalyzeEndpoint(): void
    {
        $payload = [
            'organism' => 'Bacillus subtilis',
            'naming' => 'generic',
            'data' => "RibA -> RibB -> RibD -> RibH",
            'scope' => 'open',
            'vitamins' => ['B2'],
        ];

        $response = $this->callApi('POST', '/api/analyze', $payload);
        $this->assertTrue($response['ok']);
        $this->assertArrayHasKey('vitamins', $response['data']);
        $this->assertSame('BLOCKED', $response['data']['vitamins']['B2']['q4_call']['code']);
    }

    public function testSaveRetrieveAndDeleteWorkflow(): void
    {
        $payload = [
            'organism' => 'Save Test Microbe',
            'data' => "RibA -> RibB -> RibD -> RibH -> RibE",
            'scope' => 'closed',
            'genome_type' => 'complete',
            'completeness' => 100.0,
            'vitamins' => ['B2'],
        ];

        // 1. Save
        $saveResp = $this->callApi('POST', '/api/analyses', $payload);
        $this->assertTrue($saveResp['ok']);
        $shareToken = $saveResp['data']['share_token'];
        $deleteToken = $saveResp['data']['delete_token'];
        $this->assertNotNull($shareToken);

        // 2. Retrieve
        $loadResp = $this->callApi('GET', "/api/analyses/{$shareToken}");
        $this->assertTrue($loadResp['ok']);
        $this->assertSame('Save Test Microbe', $loadResp['data']['input']['organism']);
        $this->assertSame('PROTOTROPH', $loadResp['data']['vitamins']['B2']['q4_call']['code']);

        // 3. Export JSON
        $jsonExport = $this->callApiRaw('GET', "/api/export/{$shareToken}.json");
        $this->assertStringContainsString('Save Test Microbe', $jsonExport);

        // 4. Export CSV
        $csvExport = $this->callApiRaw('GET', "/api/export/{$shareToken}.csv");
        $this->assertStringContainsString('PROTOTROPH', $csvExport);

        // 5. Delete with delete token
        $_SERVER['HTTP_X_DELETE_TOKEN'] = $deleteToken;
        $delResp = $this->callApi('DELETE', "/api/analyses/{$shareToken}");
        $this->assertTrue($delResp['ok']);
        $this->assertTrue($delResp['data']['deleted']);
        unset($_SERVER['HTTP_X_DELETE_TOKEN']);

        // 6. Verify subsequent load returns 404
        $afterDelResp = $this->callApi('GET', "/api/analyses/{$shareToken}");
        $this->assertFalse($afterDelResp['ok']);
        $this->assertSame('NOT_FOUND', $afterDelResp['error']['code']);
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
