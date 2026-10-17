<?php

declare(strict_types=1);

namespace VitaGap\Tests\Golden;

use VitaGap\Engine\Analyzer;
use VitaGap\Engine\EvidenceScorer;
use VitaGap\Engine\RuleEngine;
use VitaGap\Tests\TestCase;

final class GoldenTest1NoteExampleTest extends TestCase
{
    private Analyzer $analyzer;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer();
    }

    public function testOpenWorldWorkedExample(): void
    {
        $input = "RibA -> RibB -> RibD -> RibH";

        $report = $this->analyzer->analyze([
            'organism' => 'Example Bacterium',
            'naming' => 'generic',
            'genome_type' => 'unknown',
            'completeness' => null,
            'scope' => 'open',
            'data' => $input,
            'vitamins' => ['B2'],
        ]);

        $b2 = $report['vitamins']['B2'];
        $this->assertNotNull($b2, 'B2 report should exist');

        // Verify completeness numbers from §9.7
        $comp = $b2['q3_completeness'];
        $this->assertSame(80.0, $comp['weighted_pct'], 'Weighted completeness should be 80% (8/10)');
        $this->assertSame(83.3, $comp['unweighted_pct'], 'Unweighted completeness should be 83.3% (5/6)');
        $this->assertSame(75.0, $comp['key_coverage_pct'], 'Key coverage should be 75% (3/4)');

        // Verify call
        $call = $b2['q4_call'];
        $this->assertSame(RuleEngine::CALL_BLOCKED, $call['code']);
        $this->assertSame('Key step not found in your list', $call['label']);
        $this->assertSame('B2.R3', $call['rule_id']);

        // Verify Q2 missing steps: riboflavin synthase (ribE)
        $missing = $b2['q2_missing'];
        $this->assertCount(1, $missing);
        $this->assertSame('B2.RS', $missing[0]['code']);
        $this->assertStringContainsString('Riboflavin synthase', $missing[0]['name']);
        $this->assertSame('key', $missing[0]['kind']);

        // Verify Q5 evidence score in open-world: 0 (Weak)
        $evidence = $b2['q5_evidence'];
        $this->assertSame(0, $evidence['score']);
        $this->assertSame(EvidenceScorer::TIER_WEAK, $evidence['tier']);
    }

    public function testClosedWorldWorkedExample(): void
    {
        $input = "RibA -> RibB -> RibD -> RibH";

        $report = $this->analyzer->analyze([
            'organism' => 'Example Complete Bacterium',
            'naming' => 'generic',
            'genome_type' => 'complete',
            'completeness' => 100.0,
            'scope' => 'closed',
            'data' => $input,
            'vitamins' => ['B2'],
        ]);

        $b2 = $report['vitamins']['B2'];
        $this->assertNotNull($b2);

        // Verify call
        $call = $b2['q4_call'];
        $this->assertSame(RuleEngine::CALL_BLOCKED, $call['code']);
        $this->assertSame('Blocked at a key step', $call['label']);

        // In closed-world, evidence = 100 * 0.8 * 1.0 = 80 (Strong)
        $evidence = $b2['q5_evidence'];
        $this->assertSame(80, $evidence['score']);
        $this->assertSame(EvidenceScorer::TIER_STRONG, $evidence['tier']);
    }
}
