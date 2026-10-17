<?php

declare(strict_types=1);

namespace VitaGap\Engine;

final class EvidenceScorer
{
    public const TIER_STRONG = 'Strong';
    public const TIER_MODERATE = 'Moderate';
    public const TIER_WEAK = 'Weak';

    /**
     * Compute evidence score and contribution details.
     *
     * @param string $callCode
     * @param array<string, mixed> $bestPathway
     * @param array{
     *   scope: string,
     *   genome_type: string,
     *   completeness: ?float
     * } $context
     * @param bool $transporterFound
     * @return array{
     *   score: int,
     *   tier: string,
     *   e_found: float,
     *   absence_reliability: float,
     *   a_path: float,
     *   contributions: array<array{step: string, kind: string, status: string, weight: float, q: float, contribution: string}>
     * }
     */
    public function score(
        string $callCode,
        array $bestPathway,
        array $context,
        bool $transporterFound = false
    ): array {
        $scope = strtolower($context['scope'] ?? 'open');
        $genomeType = strtolower($context['genome_type'] ?? 'unknown');
        $checkm = $context['completeness'] ?? null;

        // Determine absence reliability 'a'
        $a = match ($scope) {
            'open' => 0.0,
            default => match ($genomeType) {
                'complete' => 1.0,
                'draft' => 0.9,
                'mag' => ($checkm !== null) ? min(1.0, max(0.0, $checkm / 100.0)) : 0.8,
                default => 0.8,
            },
        };

        // Determine pathway locus count 'k'
        $k = (int) ($bestPathway['loci_k'] ?? 1);
        $aPath = 1.0 - pow(1.0 - $a, $k);

        // Compute E_found (weighted mean q over found steps)
        $cw = (float) ($bestPathway['completeness']['weighted'] ?? 0.0);
        $sumFoundWeights = 0.0;
        $sumWeightedQ = 0.0;
        $contributions = [];

        foreach ($bestPathway['steps'] ?? [] as $step) {
            $kind = $step['kind'] ?? 'normal';
            $weight = PathwayEvaluator::KIND_WEIGHTS[$kind] ?? 1.0;
            $status = $step['status'] ?? 'missing';
            $stepQ = 0.0;

            if ($status === 'present' || $status === 'partial') {
                $matched = $step['matched'] ?? [];
                if (count($matched) > 0) {
                    $maxQ = 0.0;
                    foreach ($matched as $m) {
                        $q = (float) ($m['q'] ?? 0.8);
                        if ($q > $maxQ) {
                            $maxQ = $q;
                        }
                    }
                    $stepQ = $maxQ;
                } else {
                    $stepQ = 0.8;
                }

                if ($kind !== 'gap' && $kind !== 'activation' && $kind !== 'optional') {
                    $sumFoundWeights += $weight;
                    $sumWeightedQ += ($weight * $stepQ);
                }
            }

            $contributions[] = [
                'step' => $step['code'],
                'name' => $step['name'],
                'kind' => $kind,
                'status' => $status,
                'weight' => $weight,
                'q' => $stepQ,
                'contribution' => ($status === 'present')
                    ? sprintf("Present (q=%.2f, weight=%.1f)", $stepQ, $weight)
                    : (($status === 'partial')
                        ? sprintf("Partial (q=%.2f, weight=%.1f)", $stepQ, $weight)
                        : "Missing"),
            ];
        }

        $eFound = ($sumFoundWeights > 0.0) ? ($sumWeightedQ / $sumFoundWeights) : 0.0;

        // Compute raw score based on call code
        $rawScore = match ($callCode) {
            RuleEngine::CALL_PROTOTROPH,
            RuleEngine::CALL_PROBABLE_PROTOTROPH => 100.0 * $eFound * $cw,

            RuleEngine::CALL_BLOCKED,
            RuleEngine::CALL_PRECURSOR_DEPENDENT => 100.0 * $eFound * $a,

            RuleEngine::CALL_AUXOTROPH,
            RuleEngine::CALL_NOT_REQUIRED => min(100.0, (100.0 * $aPath) + ($transporterFound ? 10.0 : 0.0)),

            default => 0.0,
        };

        $finalScore = (int) round(min(100.0, max(0.0, $rawScore)));

        $tier = match (true) {
            $finalScore >= 75 => self::TIER_STRONG,
            $finalScore >= 50 => self::TIER_MODERATE,
            default => self::TIER_WEAK,
        };

        return [
            'score' => $finalScore,
            'tier' => $tier,
            'e_found' => round($eFound, 4),
            'absence_reliability' => round($a, 4),
            'a_path' => round($aPath, 4),
            'contributions' => $contributions,
        ];
    }
}
