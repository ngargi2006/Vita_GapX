<?php

declare(strict_types=1);

$startTime = microtime(true);

require_once __DIR__ . '/../../src/autoload.php';

use VitaGap\Db\AnalysisRepository;
use VitaGap\Db\BatchRepository;
use VitaGap\Engine\Analyzer;
use VitaGap\Engine\BatchProcessor;
use VitaGap\Engine\Matcher;
use VitaGap\Http\ExportHandler;
use VitaGap\Http\JsonResponse;
use VitaGap\Http\RateLimiter;
use VitaGap\Http\Router;
use VitaGap\Http\Validator;
use VitaGap\Input\FormatDetector;
use VitaGap\Input\InputReader;
use VitaGap\Input\Token;
use VitaGap\Kb\KbCache;

// Global exception handling
set_exception_handler(static function (Throwable $e) use ($startTime): void {
    JsonResponse::error(
        JsonResponse::CODE_SERVER_ERROR,
        getenv('APP_DEBUG') === 'true' ? $e->getMessage() : 'An internal server error occurred.',
        500,
        getenv('APP_DEBUG') === 'true' ? ['trace' => $e->getTraceAsString(), 'file' => $e->getFile(), 'line' => $e->getLine()] : [],
        [],
        $startTime
    );
});

// Helper to parse JSON or POST body
$getJsonBody = static function (): array {
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return array_merge($_POST, $data);
        }
    }
    return $_POST;
};

$getClientIp = static function (): string {
    $header = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $parts = explode(',', $header);
    return trim($parts[0]);
};

$kb = KbCache::load();
$rateLimiter = new RateLimiter();
$analysisRepo = new AnalysisRepository();
$batchRepo = new BatchRepository();
$exportHandler = new ExportHandler($analysisRepo);
$analyzer = new Analyzer($kb);
$batchProcessor = new BatchProcessor($analyzer);

$router = new Router();

// 1. Health check
$router->get('/api/health', static function (array $params, float $start): void {
    JsonResponse::success([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'service' => 'Vita-Gap API',
        'engine_version' => getenv('ENGINE_VERSION') ?: '1.0.0',
        'kb_version' => getenv('KB_VERSION') ?: '1.0.0',
        'php_version' => PHP_VERSION,
        'system' => PHP_OS_FAMILY,
    ], 200, [], $start);
});

// 2. List vitamins
$router->get('/api/vitamins', static function (array $params, float $start) use ($kb): void {
    $vitamins = [];
    foreach ($kb['vitamins'] ?? [] as $v) {
        $pathways = [];
        foreach ($v['pathways'] ?? [] as $pw) {
            $pathways[] = [
                'code' => $pw['code'],
                'name' => $pw['name'],
                'role' => $pw['role'],
                'kegg_modules' => $pw['kegg_modules'] ?? [],
                'step_count' => count($pw['steps'] ?? []),
            ];
        }
        $vitamins[] = [
            'code' => $v['code'],
            'name' => $v['name'],
            'active_forms' => $v['active_forms'] ?? '',
            'summary' => $v['summary'] ?? '',
            'pathways' => $pathways,
        ];
    }

    JsonResponse::success(['vitamins' => $vitamins], 200, [], $start);
});

// 3. Get pathway details
$router->get('/api/pathways/{code}', static function (array $params, float $start) use ($kb): void {
    $code = strtoupper(trim($params['code'] ?? ''));
    foreach ($kb['vitamins'] ?? [] as $v) {
        foreach ($v['pathways'] ?? [] as $pw) {
            if (strtoupper($pw['code']) === $code) {
                JsonResponse::success([
                    'vitamin_code' => $v['code'],
                    'vitamin_name' => $v['name'],
                    'pathway' => $pw,
                ], 200, [], $start);
                return;
            }
        }
    }

    JsonResponse::error(JsonResponse::CODE_NOT_FOUND, "Pathway '{$code}' not found.", 404, [], [], $start);
});

// 4. Gene / KO / EC lookup
$router->get('/api/lookup', static function (array $params, float $start) use ($kb): void {
    $query = trim($_GET['q'] ?? '');
    $naming = strtolower(trim($_GET['naming'] ?? 'generic'));

    if ($query === '') {
        JsonResponse::error(JsonResponse::CODE_INVALID_INPUT, "Query parameter 'q' is required.", 400, [], [], $start);
        return;
    }

    $token = (new \VitaGap\Input\Tokenizer())->classify($query);
    $matcher = new Matcher($kb);
    $matchResult = $matcher->match([$token], $naming);

    JsonResponse::success([
        'query' => $query,
        'classified_type' => $token->type,
        'naming_convention' => $naming,
        'matches' => $matchResult['matched_kos'],
        'ambiguities' => $matchResult['ambiguities'],
        'unknowns' => $matchResult['unknowns'],
    ], 200, [], $start);
});

// 5. Dry-run Parse
$router->post('/api/parse', static function (array $params, float $start) use ($getJsonBody, $kb): void {
    $input = $getJsonBody();
    $raw = (string) ($input['data'] ?? '');
    if (trim($raw) === '') {
        JsonResponse::error(JsonResponse::CODE_INVALID_INPUT, "Parameter 'data' is required.", 400, [], [], $start);
        return;
    }

    $format = (string) ($input['format'] ?? FormatDetector::FORMAT_AUTO);
    $naming = (string) ($input['naming'] ?? 'generic');
    $resolutions = is_array($input['resolve'] ?? null) ? $input['resolve'] : [];

    try {
        $reader = new InputReader();
        $readResult = $reader->read($raw, $format);
        $matcher = new Matcher($kb);
        $matchResult = $matcher->match($readResult['tokens'], $naming, $resolutions);

        JsonResponse::success([
            'detected_format' => $readResult['format'],
            'total_tokens' => count($readResult['tokens']),
            'matched_kos' => $matchResult['matched_kos'],
            'ambiguities' => $matchResult['ambiguities'],
            'unknowns' => $matchResult['unknowns'],
        ], 200, [], $start);
    } catch (InvalidArgumentException $e) {
        JsonResponse::error(JsonResponse::CODE_INVALID_INPUT, $e->getMessage(), 400, [], [], $start);
    }
});

// 6. Full Analysis (in-memory, not saved)
$router->post('/api/analyze', static function (array $params, float $start) use ($getJsonBody, $getClientIp, $rateLimiter, $analyzer): void {
    $ip = $getClientIp();
    if (!$rateLimiter->check($ip)) {
        JsonResponse::error(JsonResponse::CODE_RATE_LIMITED, "Rate limit exceeded (60 analyses/hour).", 429, [], [], $start);
        return;
    }

    $rawInput = $getJsonBody();

    try {
        $validated = Validator::validateAnalysisInput($rawInput);
        $report = $analyzer->analyze($validated);
        JsonResponse::success($report, 200, [], $start);
    } catch (InvalidArgumentException $e) {
        JsonResponse::error(JsonResponse::CODE_INVALID_INPUT, $e->getMessage(), 400, [], [], $start);
    }
});

// 7. Save Analysis
$router->post('/api/analyses', static function (array $params, float $start) use ($getJsonBody, $getClientIp, $rateLimiter, $analyzer, $analysisRepo): void {
    $ip = $getClientIp();
    if (!$rateLimiter->check($ip)) {
        JsonResponse::error(JsonResponse::CODE_RATE_LIMITED, "Rate limit exceeded.", 429, [], [], $start);
        return;
    }

    $rawInput = $getJsonBody();

    try {
        $validated = Validator::validateAnalysisInput($rawInput);
        $report = $analyzer->analyze($validated);
        $saved = $analysisRepo->save($report, $validated);

        JsonResponse::success([
            'id' => $saved['id'],
            'share_token' => $saved['share_token'],
            'delete_token' => $saved['delete_token'],
            'share_url' => "/report.html?token={$saved['share_token']}",
            'report' => $report,
        ], 201, [], $start);
    } catch (InvalidArgumentException $e) {
        JsonResponse::error(JsonResponse::CODE_INVALID_INPUT, $e->getMessage(), 400, [], [], $start);
    }
});

// 8. Load saved analysis
$router->get('/api/analyses/{share_token}', static function (array $params, float $start) use ($analysisRepo): void {
    $token = trim($params['share_token'] ?? '');
    $report = $analysisRepo->findByShareToken($token);

    if ($report === null) {
        JsonResponse::error(JsonResponse::CODE_NOT_FOUND, "Saved analysis '{$token}' not found.", 404, [], [], $start);
        return;
    }

    JsonResponse::success($report, 200, [], $start);
});

// 9. Delete saved analysis
$router->delete('/api/analyses/{share_token}', static function (array $params, float $start) use ($getJsonBody, $analysisRepo): void {
    $shareToken = trim($params['share_token'] ?? '');
    $body = $getJsonBody();
    $deleteToken = $_SERVER['HTTP_X_DELETE_TOKEN'] ?? $_GET['delete_token'] ?? ($body['delete_token'] ?? '');

    if ($deleteToken === '') {
        JsonResponse::error(JsonResponse::CODE_INVALID_INPUT, "Delete token is required to delete this analysis.", 400, [], [], $start);
        return;
    }

    $deleted = $analysisRepo->delete($shareToken, (string) $deleteToken);
    if (!$deleted) {
        JsonResponse::error(JsonResponse::CODE_NOT_FOUND, "Invalid delete token or analysis not found.", 404, [], [], $start);
        return;
    }

    JsonResponse::success(['deleted' => true, 'share_token' => $shareToken], 200, [], $start);
});

// 10. Export JSON
$router->get('/api/export/{share_token}.json', static function (array $params, float $start) use ($exportHandler): void {
    $token = trim($params['share_token'] ?? '');
    $exportHandler->exportJson($token);
});

// 11. Export CSV
$router->get('/api/export/{share_token}.csv', static function (array $params, float $start) use ($exportHandler): void {
    $token = trim($params['share_token'] ?? '');
    $exportHandler->exportCsv($token);
});

// 12. Batch Multi-Genome Community Analysis
$router->post('/api/batch', static function (array $params, float $start) use ($getJsonBody, $getClientIp, $rateLimiter, $batchProcessor, $batchRepo): void {
    $ip = $getClientIp();
    if (!$rateLimiter->check($ip)) {
        JsonResponse::error(JsonResponse::CODE_RATE_LIMITED, "Rate limit exceeded.", 429, [], [], $start);
        return;
    }

    $body = $getJsonBody();
    try {
        $result = $batchProcessor->process($body);
        $saved = $batchRepo->save($result);
        JsonResponse::success([
            'share_token' => $saved['share_token'],
            'result' => $result,
        ], 200, [], $start);
    } catch (\InvalidArgumentException $e) {
        JsonResponse::error(JsonResponse::CODE_INVALID_INPUT, $e->getMessage(), 400, [], [], $start);
    }
});

// 13. Load Saved Batch
$router->get('/api/batch/{share_token}', static function (array $params, float $start) use ($batchRepo): void {
    $token = trim($params['share_token'] ?? '');
    $result = $batchRepo->findByShareToken($token);

    if ($result === null) {
        JsonResponse::error(JsonResponse::CODE_NOT_FOUND, "Batch '{$token}' not found.", 404, [], [], $start);
        return;
    }

    JsonResponse::success(['result' => $result], 200, [], $start);
});

// 14. Export Batch Network as GraphML
$router->get('/api/export/batch/{share_token}.graphml', static function (array $params, float $start) use ($batchRepo, $batchProcessor): void {
    $token = trim($params['share_token'] ?? '');
    $result = $batchRepo->findByShareToken($token);
    if ($result === null) {
        JsonResponse::error(JsonResponse::CODE_NOT_FOUND, "Batch '{$token}' not found.", 404);
        return;
    }

    $xml = $batchProcessor->toGraphML($result);
    if (!headers_sent()) {
        header('Content-Type: application/xml; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"vitagap_community_{$token}.graphml\"");
        header('X-Content-Type-Options: nosniff');
    }
    echo $xml;
    if (JsonResponse::$shouldExit) {
        exit;
    }
});

// 15. Export Batch Community Matrix as CSV
$router->get('/api/export/batch/{share_token}.csv', static function (array $params, float $start) use ($batchRepo): void {
    $token = trim($params['share_token'] ?? '');
    $result = $batchRepo->findByShareToken($token);
    if ($result === null) {
        JsonResponse::error(JsonResponse::CODE_NOT_FOUND, "Batch '{$token}' not found.", 404);
        return;
    }

    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"vitagap_community_{$token}.csv\"");
        header('X-Content-Type-Options: nosniff');
    }

    $out = fopen('php://output', 'w');
    if ($out === false) {
        return;
    }

    fputcsv($out, ['Genome_ID', 'Genome_Name', 'Vitamin', 'Call', 'Completeness_Pct', 'Evidence_Score', 'Transporter_Found'], ',', '"', "\\");
    foreach ($result['matrix'] ?? [] as $row) {
        foreach ($row['vitamins'] ?? [] as $vCode => $v) {
            fputcsv($out, [
                $row['id'],
                $row['name'],
                $vCode,
                $v['call'],
                $v['weighted_pct'],
                $v['evidence_score'],
                !empty($v['transporter_found']) ? '1' : '0',
            ], ',', '"', "\\");
        }
    }

    fclose($out);
    if (JsonResponse::$shouldExit) {
        exit;
    }
});

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/api/health';

$router->dispatch($method, $uri, $startTime);
