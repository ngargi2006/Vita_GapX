<?php

declare(strict_types=1);

namespace VitaGap\Tests;

use Exception;

abstract class TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    protected function assertTrue(mixed $condition, string $message = ''): void
    {
        if ($condition !== true) {
            throw new Exception($message ?: 'Failed asserting that condition is true.');
        }
    }

    protected function assertFalse(mixed $condition, string $message = ''): void
    {
        if ($condition !== false) {
            throw new Exception($message ?: 'Failed asserting that condition is false.');
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            $msg = $message ?: sprintf(
                "Failed asserting that %s matches expected %s.",
                json_encode($actual),
                json_encode($expected)
            );
            throw new Exception($msg);
        }
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $msg = $message ?: sprintf(
                "Failed asserting that %s is identical to expected %s.",
                json_encode($actual),
                json_encode($expected)
            );
            throw new Exception($msg);
        }
    }

    protected function assertNull(mixed $actual, string $message = ''): void
    {
        if ($actual !== null) {
            throw new Exception($message ?: sprintf('Failed asserting that %s is null.', json_encode($actual)));
        }
    }

    protected function assertNotNull(mixed $actual, string $message = ''): void
    {
        if ($actual === null) {
            throw new Exception($message ?: 'Failed asserting that value is not null.');
        }
    }

    protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            $msg = $message ?: sprintf("Failed asserting that '%s' contains '%s'.", $haystack, $needle);
            throw new Exception($msg);
        }
    }

    /**
     * @param array<mixed> $array
     */
    protected function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            $msg = $message ?: sprintf("Failed asserting that array contains key '%s'.", (string) $key);
            throw new Exception($msg);
        }
    }

    /**
     * @param \Countable|array<mixed> $countable
     */
    protected function assertCount(int $expectedCount, \Countable|array $countable, string $message = ''): void
    {
        $actualCount = count($countable);
        if ($actualCount !== $expectedCount) {
            $msg = $message ?: sprintf("Failed asserting that count %d matches expected %d.", $actualCount, $expectedCount);
            throw new Exception($msg);
        }
    }
}
