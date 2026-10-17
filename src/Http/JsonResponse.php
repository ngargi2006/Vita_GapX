<?php

declare(strict_types=1);

namespace VitaGap\Http;

final class JsonResponse
{
    public const CODE_INVALID_INPUT = 'INVALID_INPUT';
    public const CODE_AMBIGUOUS_TOKENS = 'AMBIGUOUS_TOKENS';
    public const CODE_TOO_LARGE = 'TOO_LARGE';
    public const CODE_RATE_LIMITED = 'RATE_LIMITED';
    public const CODE_NOT_FOUND = 'NOT_FOUND';
    public const CODE_SERVER_ERROR = 'SERVER_ERROR';

    /**
     * Send a successful JSON response with standard envelope.
     *
     * @param array<string, mixed>|object|null $data
     * @param array<string, mixed> $customMeta
     */
    public static function success(
        array|object|null $data,
        int $statusCode = 200,
        array $customMeta = [],
        float $startTime = 0.0
    ): void {
        self::emit([
            'ok' => true,
            'data' => $data,
            'error' => null,
            'meta' => self::buildMeta($customMeta, $startTime),
        ], $statusCode);
    }

    /**
     * Send an error JSON response with standard envelope.
     *
     * @param array<string, mixed> $details
     * @param array<string, mixed> $customMeta
     */
    public static function error(
        string $code,
        string $message,
        int $statusCode = 400,
        array $details = [],
        array $customMeta = [],
        float $startTime = 0.0
    ): void {
        $errorPayload = [
            'code' => $code,
            'message' => $message,
        ];

        if (!empty($details)) {
            $errorPayload['details'] = $details;
        }

        self::emit([
            'ok' => false,
            'data' => null,
            'error' => $errorPayload,
            'meta' => self::buildMeta($customMeta, $startTime),
        ], $statusCode);
    }

    public static bool $shouldExit = true;

    /**
     * @param array<string, mixed> $payload
     */
    private static function emit(array $payload, int $statusCode): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header("Content-Security-Policy: default-src 'self' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self'");
            header('X-Frame-Options: DENY');
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo $json !== false ? $json : '{"ok":false,"error":{"code":"SERVER_ERROR","message":"JSON encoding error"}}';
        
        if (self::$shouldExit) {
            exit;
        }
    }

    /**
     * @param array<string, mixed> $customMeta
     * @return array<string, mixed>
     */
    private static function buildMeta(array $customMeta, float $startTime): array
    {
        $durationMs = $startTime > 0.0 ? (int) round((microtime(true) - $startTime) * 1000) : 0;
        $requestId = bin2hex(random_bytes(8));

        return array_merge([
            'request_id' => $requestId,
            'engine_version' => getenv('ENGINE_VERSION') ?: '1.0.0',
            'kb_version' => getenv('KB_VERSION') ?: '1.0.0',
            'ms' => $durationMs,
        ], $customMeta);
    }
}
