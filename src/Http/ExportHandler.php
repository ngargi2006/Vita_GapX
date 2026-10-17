<?php

declare(strict_types=1);

namespace VitaGap\Http;

use VitaGap\Db\AnalysisRepository;

final class ExportHandler
{
    public function __construct(
        private readonly AnalysisRepository $repository = new AnalysisRepository()
    ) {
    }

    /**
     * Export analysis as downloadable JSON file.
     */
    public function exportJson(string $shareToken): void
    {
        $report = $this->repository->findByShareToken($shareToken);
        if ($report === null) {
            JsonResponse::error(JsonResponse::CODE_NOT_FOUND, "Analysis with share token '{$shareToken}' not found.", 404);
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"vitagap_report_{$shareToken}.json\"");
            header('X-Content-Type-Options: nosniff');
        }

        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (JsonResponse::$shouldExit) {
            exit;
        }
    }

    /**
     * Export analysis as downloadable CSV file.
     */
    public function exportCsv(string $shareToken): void
    {
        $report = $this->repository->findByShareToken($shareToken);
        if ($report === null) {
            JsonResponse::error(JsonResponse::CODE_NOT_FOUND, "Analysis with share token '{$shareToken}' not found.", 404);
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"vitagap_report_{$shareToken}.csv\"");
            header('X-Content-Type-Options: nosniff');
        }

        $out = fopen('php://output', 'w');
        if ($out === false) {
            return;
        }

        // Header
        fputcsv($out, [
            'Vitamin',
            'Vitamin_Name',
            'Best_Pathway_Code',
            'Best_Pathway_Name',
            'Weighted_Completeness_Pct',
            'Unweighted_Completeness_Pct',
            'Key_Coverage_Pct',
            'Call_Code',
            'Call_Label',
            'Evidence_Score',
            'Evidence_Tier',
            'Missing_Key_Steps',
        ], ',', '"', "\\");

        foreach ($report['vitamins'] ?? [] as $vCode => $vData) {
            $missingKeyNames = [];
            foreach ($vData['q2_missing'] ?? [] as $m) {
                if (($m['kind'] ?? '') === 'key') {
                    $missingKeyNames[] = $m['name'] . ' (' . $m['code'] . ')';
                }
            }

            fputcsv($out, [
                $vCode,
                $vData['vitamin']['name'] ?? '',
                $vData['best_pathway']['code'] ?? '',
                $vData['best_pathway']['name'] ?? '',
                $vData['q3_completeness']['weighted_pct'] ?? 0.0,
                $vData['q3_completeness']['unweighted_pct'] ?? 0.0,
                $vData['q3_completeness']['key_coverage_pct'] ?? 0.0,
                $vData['q4_call']['code'] ?? '',
                $vData['q4_call']['label'] ?? '',
                $vData['q5_evidence']['score'] ?? 0,
                $vData['q5_evidence']['tier'] ?? '',
                implode('; ', $missingKeyNames),
            ], ',', '"', "\\");
        }

        fclose($out);
        if (JsonResponse::$shouldExit) {
            exit;
        }
    }
}
