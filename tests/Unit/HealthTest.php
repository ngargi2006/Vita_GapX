<?php

declare(strict_types=1);

namespace VitaGap\Tests\Unit;

use VitaGap\Tests\TestCase;

final class HealthTest extends TestCase
{
    public function testHealthEndpointReturnsValidStructure(): void
    {
        \VitaGap\Http\JsonResponse::$shouldExit = false;
        ob_start();
        
        // Simulate execution
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/health';
        
        // Buffer output from public/api/index.php
        include __DIR__ . '/../../public/api/index.php';
        
        $output = ob_get_clean();
        
        $this->assertNotNull($output, 'Output should not be null');
        $data = json_decode((string) $output, true);
        
        $this->assertNotNull($data, 'Output should be valid JSON');
        $this->assertTrue($data['ok'] ?? false, 'Response ok should be true');
        $this->assertTrue(array_key_exists('error', $data), 'Response should have error key');
        $this->assertNull($data['error'], 'Response error should be null');
        $this->assertSame('healthy', $data['data']['status'] ?? '', 'Status should be healthy');
        $this->assertSame('1.0.0', $data['meta']['engine_version'] ?? '', 'Engine version should be 1.0.0');
        $this->assertSame('1.0.0', $data['meta']['kb_version'] ?? '', 'KB version should be 1.0.0');
    }
}
