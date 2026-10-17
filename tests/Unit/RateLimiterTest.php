<?php

declare(strict_types=1);

namespace VitaGap\Tests\Unit;

use VitaGap\Http\RateLimiter;
use VitaGap\Tests\TestCase;

final class RateLimiterTest extends TestCase
{
    public function testRateLimiterAllowsUpToLimit(): void
    {
        $limiter = new RateLimiter(limit: 3, window: 60, salt: 'test_salt_' . time());
        $testIp = '192.168.1.100';

        $this->assertTrue($limiter->check($testIp), 'Hit 1 should pass');
        $this->assertTrue($limiter->check($testIp), 'Hit 2 should pass');
        $this->assertTrue($limiter->check($testIp), 'Hit 3 should pass');
        $this->assertFalse($limiter->check($testIp), 'Hit 4 should be rejected by rate limit');
    }
}
