<?php

declare(strict_types=1);

namespace VitaGap\Engine;

final class ReportBuilder
{
    public const ENGINE_VERSION = '1.0.0';

    /**
     * @param array<string, mixed> $kb
     */
    public function __construct(
        private readonly array $kb
    ) {
    }

    /**
     * Build the complete Vita-Gap report structure answering Q1-Q5.
     *
     * @param array{
     *   organism: string,
     *   naming: string,
     *   genome_type: string,
     *   completeness: ?float,
     *   scope: string,
     *   format: string,
     *   input_sha256: string,
     *   input_text?: ?string
     * } $metadata
     * @param array<string, mixed> $matchingResult Result from Matcher::match
     * @param array<string, mixed> $evaluatedVitamins Map of vitaminCode => evaluation data
     * @param array<string, array{call: array<string, mixed>, evidence: array<string, mixed>}> $callsAndEvidence
     * @return array<string, mixed>
     */
    public function build(
        array $metadata,
        array $matchingResult,
        array $evaluatedVitamins,
        array $callsAndEvidence
    ): array {
        $kbVersion = $this->kb['kb_version'] ?? '1.0.0';
        $keggRelease = $this->kb['kegg_release'] ?? 'Release 112.0/Oct 2026';
        $scope = strtolower($metadata['scope'] ?? 'open');
        $genomeType = strtolower($metadata['genome_type'] ?? 'unknown');
        $checkm = $metadata['completeness'] ?? null;
        $naming = $metadata['naming'] ?? 'generic';

        $vitaminReports = [];
        $barcode = [];

        foreach ($evaluatedVitamins as $vCode => $vEval) {
            $callInfo = $callsAndEvidence[$vCode]['call'] ?? [
                'code' => RuleEngine::CALL_UNCERTAIN,
                'label' => RuleEngine::LABELS[RuleEngine::CALL_UNCERTAIN],
                'rule_id' => 'FALLBACK',
                'reasons' => [],
            ];
            $evidenceInfo = $callsAndEvidence[$vCode]['evidence'] ?? [
                'score' => 0,
                'tier' => EvidenceScorer::TIER_WEAK,
                'contributions' => [],
            ];

            $bestPw = $vEval['best_pathway'];
            $comp = $bestPw['completeness'] ?? [
                'weighted' => 0.0,
                'unweighted' => 0.0,
                'key_coverage' => 0.0,
                'present_steps' => 0,
                'total_steps' => 0,
            ];

            // Q1: Detected steps
            $detectedSteps = [];
            // Q2: Missing steps
            $missingSteps = [];

            if ($bestPw !== null) {
                foreach ($bestPw['steps'] as $s) {
                    if ($s['status'] === 'present' || $s['status'] === 'partial') {
                        $detectedSteps[] = [
                            'code' => $s['code'],
                            'name' => $s['name'],
                            'ec' => $s['ec'],
                            'kind' => $s['kind'],
                            'branch' => $s['branch'],
                            'status' => $s['status'],
                            'score' => $s['score'],
                            'matched' => $s['matched'],
                        ];
                    } elseif ($s['kind'] !== 'gap') {
                        $missingSteps[] = [
                            'code' => $s['code'],
                            'name' => $s['name'],
                            'ec' => $s['ec'],
                            'kind' => $s['kind'],
                            'branch' => $s['branch'],
                            'status' => $s['status'],
                            'required_options' => $s['options'],
                            'verify_hint' => "BLAST reference protein from KEGG against the genome; inspect contig edges, assembly breaks, and potential pseudogenes.",
                            'wet_lab_check' => "Assess microbial growth in vitamin-free chemically defined minimal medium with and without supplementation of this vitamin.",
                        ];
                    }
                }
            }

            // Limitations specific to this vitamin
            $limitations = $this->generateLimitations($scope, $genomeType, $checkm, $bestPw, $matchingResult);

            $vitaminReports[$vCode] = [
                'vitamin' => [
                    'code' => $vCode,
                    'name' => $vEval['vitamin_name'],
                    'active_forms' => $vEval['active_forms'],
                ],
                'best_pathway' => [
                    'code' => $bestPw['code'] ?? '',
                    'name' => $bestPw['name'] ?? '',
                    'kegg_modules' => $bestPw['kegg_modules'] ?? [],
                ],
                'q1_detected' => $detectedSteps,
                'q2_missing' => $missingSteps,
                'q3_completeness' => [
                    'weighted_pct' => round($comp['weighted'] * 100, 1),
                    'unweighted_pct' => round($comp['unweighted'] * 100, 1),
                    'key_coverage_pct' => round($comp['key_coverage'] * 100, 1),
                    'raw' => $comp,
                ],
                'q4_call' => $callInfo,
                'q5_evidence' => $evidenceInfo,
                'steps' => $bestPw['steps'] ?? [],
                'variants' => array_map(static fn($v) => [
                    'code' => $v['code'],
                    'name' => $v['name'],
                    'completeness' => $v['completeness'],
                ], $vEval['variants'] ?? []),
                'activation' => $vEval['activation'] ?? [],
                'transport' => $vEval['transport'] ?? [],
                'salvage' => $vEval['salvage'] ?? [],
                'dependency' => $vEval['dependency'] ?? [],
                'limitations' => $limitations,
            ];

            $barcode[] = [
                'vitamin' => $vCode,
                'call' => $callInfo['code'],
                'label' => $callInfo['label'],
                'evidence_tier' => $evidenceInfo['tier'],
                'completeness_pct' => round($comp['weighted'] * 100),
            ];
        }

        // Generate auto-written Methods paragraph
        $methodsParagraph = sprintf(
            "Vitamin biosynthesis gap analysis was performed using Vita-Gap (v%s, KB v%s, KEGG release %s). Input annotations (%s, SHA-256: %s) for organism '%s' were mapped to KEGG Orthology (KO) identifiers utilizing ambiguity-aware alias resolution under '%s' naming conventions. De novo pathway completeness was determined across variant biosynthetic routes using committed key-step weighting and known-gap registries. Phenotypic classifications were assigned via curated rule sets and evidence scores (0–100) were calibrated according to genomic assembly completeness.",
            self::ENGINE_VERSION,
            $kbVersion,
            $keggRelease,
            $metadata['format'] ?? 'auto',
            $metadata['input_sha256'],
            $metadata['organism'] ?: 'Unspecified bacterium',
            $naming
        );

        return [
            'meta' => [
                'engine_version' => self::ENGINE_VERSION,
                'kb_version' => $kbVersion,
                'kegg_release' => $keggRelease,
                'timestamp' => date('c'),
            ],
            'input' => [
                'organism' => $metadata['organism'] ?: 'Unspecified bacterium',
                'naming' => $naming,
                'genome_type' => $genomeType,
                'completeness' => $checkm,
                'scope' => $scope,
                'format' => $metadata['format'] ?? 'auto',
                'sha256' => $metadata['input_sha256'],
            ],
            'barcode' => $barcode,
            'vitamins' => $vitaminReports,
            'ambiguities' => $matchingResult['ambiguities'] ?? [],
            'unknowns' => $matchingResult['unknowns'] ?? [],
            'methods' => [
                'text' => $methodsParagraph,
                'parameters' => [
                    'scope' => $scope,
                    'genome_type' => $genomeType,
                    'completeness' => $checkm,
                    'naming' => $naming,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $bestPw
     * @param array<string, mixed> $matchingResult
     * @return array<string>
     */
    private function generateLimitations(
        string $scope,
        string $genomeType,
        ?float $checkm,
        ?array $bestPw,
        array $matchingResult
    ): array {
        $limitations = [];

        if ($scope === 'open') {
            $limitations[] = "Missing genes reflect absence from your submitted list, not proven biological absence in the bacterial genome.";
        }

        if ($genomeType === 'mag' && $checkm !== null) {
            $k = (int) ($bestPw['loci_k'] ?? 1);
            $cDecimal = max(0.0, min(1.0, $checkm / 100.0));
            $missProb = round(pow(1.0 - $cDecimal, $k) * 100, 1);
            $limitations[] = sprintf(
                "Based on CheckM completeness (%.1f%%), there is an estimated %.1f%% probability that the missing pathway locus resides in unassembled contigs.",
                $checkm,
                $missProb
            );
        }

        $hasSymbolMatches = false;
        foreach ($matchingResult['matched_kos'] ?? [] as $m) {
            foreach ($m['tokens'] ?? [] as $tok) {
                if ($tok->type === 'SYMBOL') {
                    $hasSymbolMatches = true;
                    break 2;
                }
            }
        }

        if ($hasSymbolMatches) {
            $limitations[] = "Gene symbols can be ambiguous across divergent bacterial taxa; matching on explicit KEGG Orthology (KO) IDs yields higher prediction confidence.";
        }

        $limitations[] = "Known biological gaps (e.g. unspecific phosphatases) are not penalized. Genomic presence reveals metabolic potential, not transcription, post-translational regulation, or culture medium growth.";

        return $limitations;
    }
}
