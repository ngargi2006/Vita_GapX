<?php

declare(strict_types=1);

namespace VitaGap\Engine;

final class PathwayEvaluator
{
    /**
     * Weights by step kind for completeness scoring.
     */
    public const KIND_WEIGHTS = [
        'key' => 2.0,
        'normal' => 1.0,
        'low' => 0.5,
        'gap' => 0.0,        // Excluded from standard de novo denominator
        'activation' => 0.0, // Excluded from de novo denominator
        'optional' => 0.0,   // Excluded from de novo denominator
    ];

    /**
     * @param array<string, mixed> $kb
     */
    public function __construct(
        private readonly array $kb
    ) {
    }

    /**
     * Evaluate all pathways for a specific vitamin given matched KOs.
     *
     * @param array<string, array{ko: string, q: float, tokens: array<mixed>, warnings: array<string>}> $matchedKos
     * @return array<string, mixed>
     */
    public function evaluateVitamin(string $vitaminCode, array $matchedKos): array
    {
        $vitamin = null;
        foreach ($this->kb['vitamins'] ?? [] as $v) {
            if ($v['code'] === $vitaminCode) {
                $vitamin = $v;
                break;
            }
        }

        if ($vitamin === null) {
            throw new \InvalidArgumentException("Vitamin {$vitaminCode} not found in KB.");
        }

        $deNovoEvaluations = [];
        $activationEvaluations = [];
        $transportEvaluations = [];
        $salvageEvaluations = [];
        $dependencyEvaluations = [];

        foreach ($vitamin['pathways'] ?? [] as $pw) {
            $eval = $this->evaluatePathway($pw, $matchedKos);
            $role = $pw['role'] ?? 'de_novo';

            match ($role) {
                'activation' => $activationEvaluations[] = $eval,
                'transport' => $transportEvaluations[] = $eval,
                'salvage' => $salvageEvaluations[] = $eval,
                'dependency' => $dependencyEvaluations[] = $eval,
                default => $deNovoEvaluations[] = $eval,
            };
        }

        // Pick best de_novo variant: highest weighted completeness, then key coverage, then unweighted
        $bestDeNovo = null;
        foreach ($deNovoEvaluations as $eval) {
            if ($bestDeNovo === null) {
                $bestDeNovo = $eval;
                continue;
            }

            $currCw = $eval['completeness']['weighted'];
            $bestCw = $bestDeNovo['completeness']['weighted'];

            if ($currCw > $bestCw + 0.0001) {
                $bestDeNovo = $eval;
            } elseif (abs($currCw - $bestCw) <= 0.0001) {
                // Tie-breaker 1: key coverage
                $currKc = $eval['completeness']['key_coverage'];
                $bestKc = $bestDeNovo['completeness']['key_coverage'];
                if ($currKc > $bestKc + 0.0001) {
                    $bestDeNovo = $eval;
                } elseif (abs($currKc - $bestKc) <= 0.0001) {
                    // Tie-breaker 2: unweighted completeness
                    if ($eval['completeness']['unweighted'] > $bestDeNovo['completeness']['unweighted']) {
                        $bestDeNovo = $eval;
                    }
                }
            }
        }

        return [
            'vitamin_code' => $vitaminCode,
            'vitamin_name' => $vitamin['name'],
            'active_forms' => $vitamin['active_forms'] ?? '',
            'best_pathway' => $bestDeNovo,
            'variants' => $deNovoEvaluations,
            'activation' => $activationEvaluations,
            'transport' => $transportEvaluations,
            'salvage' => $salvageEvaluations,
            'dependency' => $dependencyEvaluations,
            'all_pathways' => array_merge(
                $deNovoEvaluations,
                $activationEvaluations,
                $transportEvaluations,
                $salvageEvaluations,
                $dependencyEvaluations
            ),
        ];
    }

    /**
     * Evaluate an individual pathway.
     *
     * @param array<string, mixed> $pathway
     * @param array<string, array{ko: string, q: float, tokens: array<mixed>, warnings: array<string>}> $matchedKos
     * @return array<string, mixed>
     */
    public function evaluatePathway(array $pathway, array $matchedKos): array
    {
        $steps = [];
        $presentStepCodes = [];
        $partialStepCodes = [];
        $missingStepCodes = [];
        $stepKinds = [];
        $branches = [];

        $sumWeightedPresent = 0.0;
        $sumWeightTotal = 0.0;
        $stepsPresentCount = 0;
        $stepsEvaluatedTotal = 0;

        $keyTotal = 0;
        $keyPresent = 0;
        $missingKeyHasAlternative = false;

        foreach ($pathway['steps'] ?? [] as $stepDef) {
            $stepCode = $stepDef['code'];
            $kind = $stepDef['kind'] ?? 'normal';
            $branch = $stepDef['branch'] ?? 'main';
            $stepKinds[$stepCode] = $kind;

            if (!isset($branches[$branch])) {
                $branches[$branch] = ['total' => 0, 'present' => 0];
            }

            $options = $stepDef['options'] ?? [];
            $evaluation = $this->evaluateStepOptions($options, $matchedKos);

            $status = $evaluation['status'];
            $pScore = $evaluation['score'];
            $matchedDetails = $evaluation['matched'];
            $missingKos = $evaluation['missing'];

            $isKey = ($kind === 'key');
            $isGap = ($kind === 'gap');
            $isAux = in_array($kind, ['activation', 'optional'], true);

            $stepData = [
                'code' => $stepCode,
                'name' => $stepDef['name'],
                'ec' => $stepDef['ec'] ?? null,
                'kind' => $kind,
                'branch' => $branch,
                'status' => $status,
                'score' => $pScore,
                'matched' => $matchedDetails,
                'missing' => $missingKos,
                'options' => $options,
                'note' => $stepDef['note'] ?? null,
            ];

            $steps[] = $stepData;

            if ($status === 'present') {
                $presentStepCodes[] = $stepCode;
                $branches[$branch]['present']++;
            } elseif ($status === 'partial') {
                $partialStepCodes[] = $stepCode;
                $branches[$branch]['present'] += 0.5;
            } else {
                $missingStepCodes[] = $stepCode;
            }

            // Exclude gap, activation, and optional steps from de novo pathway completeness calculations
            if (!$isGap && !$isAux) {
                $weight = self::KIND_WEIGHTS[$kind] ?? 1.0;
                $sumWeightTotal += $weight;
                $sumWeightedPresent += ($weight * $pScore);

                $stepsEvaluatedTotal++;
                if ($pScore >= 1.0) {
                    $stepsPresentCount++;
                }

                $branches[$branch]['total']++;

                if ($isKey) {
                    $keyTotal++;
                    if ($pScore >= 1.0) {
                        $keyPresent++;
                    } else {
                        // Check if missing key step has a documented alternative option
                        if (count($options) > 1) {
                            $missingKeyHasAlternative = true;
                        }
                    }
                }
            }
        }

        $cw = ($sumWeightTotal > 0.0) ? ($sumWeightedPresent / $sumWeightTotal) : 0.0;
        $cu = ($stepsEvaluatedTotal > 0) ? ($stepsPresentCount / $stepsEvaluatedTotal) : 0.0;
        $kc = ($keyTotal > 0) ? ($keyPresent / $keyTotal) : 1.0;
        $keyMissing = $keyTotal - $keyPresent;

        // Compile branch completion status
        $branchStatus = [];
        foreach ($branches as $bName => $bData) {
            $branchStatus[$bName] = [
                'total' => $bData['total'],
                'present' => $bData['present'],
                'complete' => ($bData['total'] > 0 && $bData['present'] >= $bData['total']),
                'absent' => ($bData['present'] === 0 || $bData['present'] === 0.0),
            ];
        }

        return [
            'code' => $pathway['code'],
            'name' => $pathway['name'],
            'role' => $pathway['role'] ?? 'de_novo',
            'kegg_modules' => $pathway['kegg_modules'] ?? [],
            'loci_k' => $pathway['loci_k'] ?? 1,
            'steps' => $steps,
            'present_steps' => $presentStepCodes,
            'partial_steps' => $partialStepCodes,
            'missing_steps' => $missingStepCodes,
            'branches' => $branchStatus,
            'completeness' => [
                'weighted' => round($cw, 4),
                'unweighted' => round($cu, 4),
                'key_coverage' => round($kc, 4),
                'key_present' => $keyPresent,
                'key_total' => $keyTotal,
                'key_missing' => $keyMissing,
                'missing_key_has_alternative' => $missingKeyHasAlternative,
                'total_steps' => $stepsEvaluatedTotal,
                'present_steps' => $stepsPresentCount,
            ],
        ];
    }

    /**
     * Evaluate alternative options for a step.
     *
     * @param array<string> $options
     * @param array<string, array{ko: string, q: float, tokens: array<mixed>, warnings: array<string>}> $matchedKos
     * @return array{status: string, score: float, matched: array<mixed>, missing: array<string>}
     */
    private function evaluateStepOptions(array $options, array $matchedKos): array
    {
        if (count($options) === 0) {
            return ['status' => 'missing', 'score' => 0.0, 'matched' => [], 'missing' => []];
        }

        $bestOptionStatus = 'missing';
        $bestScore = 0.0;
        $matchedDetails = [];
        $missingAll = [];

        foreach ($options as $opt) {
            $requiredKos = array_map('trim', explode('+', $opt));
            $foundForOpt = [];
            $missingForOpt = [];

            foreach ($requiredKos as $reqKo) {
                if (isset($matchedKos[$reqKo])) {
                    $foundForOpt[] = $matchedKos[$reqKo];
                } else {
                    $missingForOpt[] = $reqKo;
                }
            }

            if (count($missingForOpt) === 0) {
                // Entire option (or complex) is satisfied!
                return [
                    'status' => 'present',
                    'score' => 1.0,
                    'matched' => $foundForOpt,
                    'missing' => [],
                ];
            }

            // Partial complex check: some subunits found in a multi-protein complex
            if (count($requiredKos) > 1 && count($foundForOpt) > 0) {
                if ($bestScore < 0.5) {
                    $bestScore = 0.5;
                    $bestOptionStatus = 'partial';
                    $matchedDetails = $foundForOpt;
                    $missingAll = $missingForOpt;
                }
            } else {
                if ($bestScore === 0.0 && count($missingAll) === 0) {
                    $missingAll = $missingForOpt;
                }
            }
        }

        return [
            'status' => $bestOptionStatus,
            'score' => $bestScore,
            'matched' => $matchedDetails,
            'missing' => $missingAll,
        ];
    }
}
