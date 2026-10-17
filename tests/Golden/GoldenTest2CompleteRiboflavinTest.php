<?php

declare(strict_types=1);

namespace VitaGap\Tests\Golden;

use VitaGap\Engine\Analyzer;
use VitaGap\Engine\EvidenceScorer;
use VitaGap\Engine\RuleEngine;
use VitaGap\Tests\TestCase;

final class GoldenTest2CompleteRiboflavinTest extends TestCase
{
    private Analyzer $analyzer;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer();
    }

    public function testCompleteRiboflavinSynthesisYieldsPrototroph(): void
    {
        // Adding ribE completes the pathway
        $input = "RibA -> RibB -> RibD -> RibH -> RibE";

        $report = $this->analyzer->analyze([
            'organism' => 'Synthesizer Bacterium',
            'naming' => 'generic',
            'genome_type' => 'complete',
            'completeness' => 100.0,
            'scope' => 'closed',
            'data' => $input,
            'vitamins' => ['B2'],
        ]);

        $b2 = $report['vitamins']['B2'];
        $this->assertNotNull($b2);

        // Verify completeness is 100%
        $comp = $b2['q3_completeness'];
        $this->assertSame(100.0, $comp['weighted_pct']);
        $this->assertSame(100.0, $comp['unweighted_pct']);
        $this->assertSame(100.0, $comp['key_coverage_pct']);

        // Verify call is PROTOTROPH
        $call = $b2['q4_call'];
        $this->assertSame(RuleEngine::CALL_PROTOTROPH, $call['code']);
        $this->assertSame('B2.R1', $call['rule_id']);

        // Verify 0 missing steps
        $this->assertCount(0, $b2['q2_missing']);

        // Evidence: 100 * 0.8 * 1.0 = 80 (Strong)
        $evidence = $b2['q5_evidence'];
        $this->assertSame(80, $evidence['score']);
        $this->assertSame(EvidenceScorer::TIER_STRONG, $evidence['tier']);
    }
}
