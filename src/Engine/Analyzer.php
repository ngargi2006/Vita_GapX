<?php

declare(strict_types=1);

namespace VitaGap\Engine;

use VitaGap\Input\FormatDetector;
use VitaGap\Input\InputReader;
use VitaGap\Kb\KbCache;

final class Analyzer
{
    private readonly array $kb;
    private readonly InputReader $reader;
    private readonly Matcher $matcher;
    private readonly PathwayEvaluator $evaluator;
    private readonly RuleEngine $ruleEngine;
    private readonly EvidenceScorer $evidenceScorer;
    private readonly ReportBuilder $reportBuilder;

    /**
     * @param array<string, mixed>|null $kb
     */
    public function __construct(?array $kb = null)
    {
        $this->kb = $kb ?? KbCache::load();
        $this->reader = new InputReader();
        $this->matcher = new Matcher($this->kb);
        $this->evaluator = new PathwayEvaluator($this->kb);
        $this->ruleEngine = new RuleEngine();
        $this->evidenceScorer = new EvidenceScorer();
        $this->reportBuilder = new ReportBuilder($this->kb);
    }

    /**
     * Run full analysis on input data.
     *
     * @param array{
     *   data: string,
     *   organism?: string,
     *   naming?: string,
     *   genome_type?: string,
     *   completeness?: ?float,
     *   scope?: string,
     *   format?: string,
     *   vitamins?: array<string>,
     *   resolve?: array<string, string>
     * } $params
     * @return array<string, mixed>
     */
    public function analyze(array $params): array
    {
        $rawInput = (string) ($params['data'] ?? '');
        $organism = (string) ($params['organism'] ?? '');
        $naming = (string) ($params['naming'] ?? 'generic');
        $genomeType = (string) ($params['genome_type'] ?? 'unknown');
        $completeness = isset($params['completeness']) && $params['completeness'] !== null && $params['completeness'] !== ''
            ? (float) $params['completeness']
            : null;
        $scope = (string) ($params['scope'] ?? 'open');
        $format = (string) ($params['format'] ?? FormatDetector::FORMAT_AUTO);
        $requestedVitamins = $params['vitamins'] ?? [];
        $resolutions = $params['resolve'] ?? [];

        $inputSha256 = hash('sha256', $rawInput);

        // 1. Read input and parse tokens
        $readResult = $this->reader->read($rawInput, $format);
        $tokens = $readResult['tokens'];
        $detectedFormat = $readResult['format'];

        // 2. Match tokens against KB
        $matchResult = $this->matcher->match($tokens, $naming, $resolutions);
        $matchedKos = $matchResult['matched_kos'];

        // 3. Determine vitamins to evaluate
        $allVitaminCodes = array_column($this->kb['vitamins'] ?? [], 'code');
        $targetVitamins = (is_array($requestedVitamins) && count($requestedVitamins) > 0)
            ? array_intersect($allVitaminCodes, $requestedVitamins)
            : $allVitaminCodes;

        $context = [
            'scope' => $scope,
            'genome_type' => $genomeType,
            'completeness' => $completeness,
        ];

        // 4. Evaluate each vitamin
        $evaluatedVitamins = [];
        $callsAndEvidence = [];

        foreach ($targetVitamins as $vCode) {
            $vEval = $this->evaluator->evaluateVitamin($vCode, $matchedKos);
            $evaluatedVitamins[$vCode] = $vEval;

            // Find vitamin KB definition
            $vDef = null;
            foreach ($this->kb['vitamins'] as $v) {
                if ($v['code'] === $vCode) {
                    $vDef = $v;
                    break;
                }
            }

            // Determine if a transporter matched
            $transporterFound = false;
            foreach ($vEval['transport'] as $tpw) {
                foreach ($tpw['steps'] as $tstep) {
                    if ($tstep['status'] === 'present') {
                        $transporterFound = true;
                        break 2;
                    }
                }
            }

            // Run Rule Engine
            $call = $this->ruleEngine->evaluate($vDef, $vEval, $context);

            // Run Evidence Scorer
            $evidence = ($vEval['best_pathway'] !== null)
                ? $this->evidenceScorer->score($call['code'], $vEval['best_pathway'], $context, $transporterFound)
                : [
                    'score' => 0,
                    'tier' => EvidenceScorer::TIER_WEAK,
                    'e_found' => 0.0,
                    'absence_reliability' => 0.0,
                    'a_path' => 0.0,
                    'contributions' => [],
                ];

            $callsAndEvidence[$vCode] = [
                'call' => $call,
                'evidence' => $evidence,
            ];
        }

        // 5. Build report
        $metadata = [
            'organism' => $organism,
            'naming' => $naming,
            'genome_type' => $genomeType,
            'completeness' => $completeness,
            'scope' => $scope,
            'format' => $detectedFormat,
            'input_sha256' => $inputSha256,
        ];

        return $this->reportBuilder->build(
            $metadata,
            $matchResult,
            $evaluatedVitamins,
            $callsAndEvidence
        );
    }
}
