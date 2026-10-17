<?php

declare(strict_types=1);

namespace VitaGap\Engine;

final class RuleEngine
{
    public const CALL_PROTOTROPH = 'PROTOTROPH';
    public const CALL_PROBABLE_PROTOTROPH = 'PROBABLE_PROTOTROPH';
    public const CALL_PRECURSOR_DEPENDENT = 'PRECURSOR_DEPENDENT';
    public const CALL_BLOCKED = 'BLOCKED';
    public const CALL_AUXOTROPH = 'AUXOTROPH';
    public const CALL_NOT_REQUIRED = 'NOT_REQUIRED';
    public const CALL_NOT_ASSESSABLE = 'NOT_ASSESSABLE';
    public const CALL_UNCERTAIN = 'UNCERTAIN';

    public const LABELS = [
        self::CALL_PROTOTROPH => 'Can make it (Prototroph)',
        self::CALL_PROBABLE_PROTOTROPH => 'Probably can make it (Probable prototroph)',
        self::CALL_PRECURSOR_DEPENDENT => 'Needs a precursor (Precursor-dependent)',
        self::CALL_BLOCKED => 'Blocked at a key step',
        self::CALL_AUXOTROPH => 'Probably needs it from surroundings (Auxotroph)',
        self::CALL_NOT_REQUIRED => 'Doesn\'t seem to need it (Not required)',
        self::CALL_NOT_ASSESSABLE => 'Not enough data (Not assessable)',
        self::CALL_UNCERTAIN => 'Uncertain status',
    ];

    /**
     * Evaluate rules for a vitamin.
     *
     * @param array<string, mixed> $vitaminDef KB definition for this vitamin
     * @param array<string, mixed> $vitaminEval Evaluated vitamin structure from PathwayEvaluator
     * @param array{
     *   scope: string,
     *   genome_type: string,
     *   completeness: ?float
     * } $context
     * @return array{
     *   code: string,
     *   label: string,
     *   rule_id: string,
     *   reasons: array<string>
     * }
     */
    public function evaluate(array $vitaminDef, array $vitaminEval, array $context): array
    {
        $rules = $vitaminDef['rules'] ?? [];
        $scope = strtolower($context['scope'] ?? 'open');
        $genomeType = strtolower($context['genome_type'] ?? 'unknown');
        $checkm = $context['completeness'] ?? null;

        $isGenomeIncomplete = (
            $genomeType === 'draft' ||
            $genomeType === 'mag' ||
            ($checkm !== null && $checkm < 95.0)
        );

        $bestPathway = $vitaminEval['best_pathway'];
        $allPresentSteps = $this->collectAllPresentStepCodes($vitaminEval);
        $allPartialSteps = $this->collectAllPartialStepCodes($vitaminEval);

        // Check if an uptake transporter was found
        $transporterFound = false;
        foreach ($vitaminEval['transport'] ?? [] as $tpw) {
            foreach ($tpw['steps'] ?? [] as $tstep) {
                if ($tstep['status'] === 'present') {
                    $transporterFound = true;
                    break 2;
                }
            }
        }

        foreach ($rules as $rule) {
            $ruleId = $rule['id'];
            $conditions = $rule['if'] ?? [];
            $call = $rule['call'];

            if ($this->matchesConditions($conditions, $bestPathway, $allPresentSteps, $allPartialSteps, $scope, $isGenomeIncomplete, $vitaminEval)) {
                $label = self::LABELS[$call] ?? $call;
                $reasons = $this->generateReasons($call, $ruleId, $bestPathway, $scope, $transporterFound);

                if ($call === self::CALL_AUXOTROPH && $transporterFound) {
                    $label .= ' · uptake system found';
                }

                if ($scope === 'open' && $call === self::CALL_BLOCKED) {
                    $label = 'Key step not found in your list';
                }

                return [
                    'code' => $call,
                    'label' => $label,
                    'rule_id' => $ruleId,
                    'reasons' => $reasons,
                ];
            }
        }

        return [
            'code' => self::CALL_UNCERTAIN,
            'label' => self::LABELS[self::CALL_UNCERTAIN],
            'rule_id' => 'DEFAULT_FALLBACK',
            'reasons' => ['No conclusive rule matched the detected gene repertoire.'],
        ];
    }

    /**
     * Check if a condition block matches.
     *
     * @param array<string, mixed> $cond
     * @param array<string, mixed>|null $bestPathway
     * @param array<string> $allPresentSteps
     * @param array<string> $allPartialSteps
     * @param array<string, mixed> $vitaminEval
     */
    private function matchesConditions(
        array $cond,
        ?array $bestPathway,
        array $allPresentSteps,
        array $allPartialSteps,
        string $scope,
        bool $isGenomeIncomplete,
        array $vitaminEval
    ): bool {
        if (empty($cond)) {
            return true;
        }

        if (isset($cond['any_of']) && is_array($cond['any_of'])) {
            $anyPassed = false;
            foreach ($cond['any_of'] as $subCond) {
                if ($this->matchesConditions($subCond, $bestPathway, $allPresentSteps, $allPartialSteps, $scope, $isGenomeIncomplete, $vitaminEval)) {
                    $anyPassed = true;
                    break;
                }
            }
            if (!$anyPassed) {
                return false;
            }
        }

        if (isset($cond['all_of']) && is_array($cond['all_of'])) {
            foreach ($cond['all_of'] as $subCond) {
                if (!$this->matchesConditions($subCond, $bestPathway, $allPresentSteps, $allPartialSteps, $scope, $isGenomeIncomplete, $vitaminEval)) {
                    return false;
                }
            }
        }

        // Scope check ('open' or 'closed')
        if (isset($cond['scope']) && $cond['scope'] !== $scope) {
            return false;
        }

        // Genome incomplete check
        if (isset($cond['genome_incomplete']) && $cond['genome_incomplete'] !== $isGenomeIncomplete) {
            return false;
        }

        // Pathway-specific check
        $targetPathway = $bestPathway;
        if (isset($cond['pathway'])) {
            $targetCode = $cond['pathway'];
            $foundTarget = null;
            foreach ($vitaminEval['all_pathways'] as $pw) {
                if ($pw['code'] === $targetCode) {
                    $foundTarget = $pw;
                    break;
                }
            }
            if ($foundTarget === null) {
                return false;
            }
            $targetPathway = $foundTarget;
        }

        if ($targetPathway !== null) {
            $comp = $targetPathway['completeness'];

            if (isset($cond['completeness_gte']) && $comp['weighted'] < (float) $cond['completeness_gte']) {
                return false;
            }

            if (isset($cond['completeness_lte']) && $comp['weighted'] > (float) $cond['completeness_lte']) {
                return false;
            }

            if (isset($cond['key_coverage_eq']) && abs($comp['key_coverage'] - (float) $cond['key_coverage_eq']) > 0.001) {
                return false;
            }

            if (isset($cond['key_coverage_lte']) && $comp['key_coverage'] > (float) $cond['key_coverage_lte']) {
                return false;
            }

            if (isset($cond['key_missing_lte']) && $comp['key_missing'] > (int) $cond['key_missing_lte']) {
                return false;
            }

            if (isset($cond['key_missing_gte']) && $comp['key_missing'] < (int) $cond['key_missing_gte']) {
                return false;
            }

            if (isset($cond['missing_key_has_alternative']) && $cond['missing_key_has_alternative'] !== $comp['missing_key_has_alternative']) {
                return false;
            }

            if (isset($cond['matched_steps_eq']) && $comp['present_steps'] !== (int) $cond['matched_steps_eq']) {
                return false;
            }
        } else {
            if (isset($cond['matched_steps_eq']) && (int) $cond['matched_steps_eq'] === 0) {
                // No pathway found and 0 matched steps
                return true;
            }
        }

        // Presence / absence of steps
        if (isset($cond['present']) && is_array($cond['present'])) {
            foreach ($cond['present'] as $stepCode) {
                if (!in_array($stepCode, $allPresentSteps, true)) {
                    return false;
                }
            }
        }

        if (isset($cond['absent']) && is_array($cond['absent'])) {
            foreach ($cond['absent'] as $stepCode) {
                if (in_array($stepCode, $allPresentSteps, true) || in_array($stepCode, $allPartialSteps, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Generate human-readable reasons for why this call was made.
     *
     * @param array<string, mixed>|null $bestPathway
     * @return array<string>
     */
    private function generateReasons(string $call, string $ruleId, ?array $bestPathway, string $scope, bool $transporterFound): array
    {
        $reasons = [];
        if ($bestPathway !== null) {
            $comp = $bestPathway['completeness'];
            $pct = round($comp['weighted'] * 100, 1);
            $reasons[] = sprintf(
                "Pathway %s exhibits %s%% weighted completeness (%d/%d steps present).",
                $bestPathway['name'],
                (string) $pct,
                $comp['present_steps'],
                $comp['total_steps']
            );

            if ($comp['key_total'] > 0) {
                $reasons[] = sprintf(
                    "%d of %d key committed steps detected (key coverage %d%%).",
                    $comp['key_present'],
                    $comp['key_total'],
                    round($comp['key_coverage'] * 100)
                );
            }

            if (count($bestPathway['missing_steps']) > 0) {
                $missingNames = [];
                foreach ($bestPathway['steps'] as $s) {
                    if ($s['status'] === 'missing' && $s['kind'] === 'key') {
                        $missingNames[] = $s['name'] . ' (' . $s['code'] . ')';
                    }
                }
                if (count($missingNames) > 0) {
                    $verb = ($scope === 'open') ? 'not found in input' : 'missing';
                    $reasons[] = sprintf("Committed step(s) %s: %s.", $verb, implode(', ', $missingNames));
                }
            }
        }

        if ($transporterFound) {
            $reasons[] = "Active vitamin uptake/transporter machinery detected.";
        }

        return $reasons;
    }

    /**
     * @param array<string, mixed> $vitaminEval
     * @return array<string>
     */
    private function collectAllPresentStepCodes(array $vitaminEval): array
    {
        $codes = [];
        foreach ($vitaminEval['all_pathways'] as $pw) {
            foreach ($pw['steps'] as $step) {
                if ($step['status'] === 'present') {
                    $codes[] = $step['code'];
                }
            }
        }
        return array_unique($codes);
    }

    /**
     * @param array<string, mixed> $vitaminEval
     * @return array<string>
     */
    private function collectAllPartialStepCodes(array $vitaminEval): array
    {
        $codes = [];
        foreach ($vitaminEval['all_pathways'] as $pw) {
            foreach ($pw['steps'] as $step) {
                if ($step['status'] === 'partial') {
                    $codes[] = $step['code'];
                }
            }
        }
        return array_unique($codes);
    }
}
