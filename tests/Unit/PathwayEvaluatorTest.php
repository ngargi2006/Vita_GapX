<?php

declare(strict_types=1);

namespace VitaGap\Tests\Unit;

use VitaGap\Engine\PathwayEvaluator;
use VitaGap\Kb\KbCache;
use VitaGap\Tests\TestCase;

final class PathwayEvaluatorTest extends TestCase
{
    private PathwayEvaluator $evaluator;

    public function setUp(): void
    {
        $kb = KbCache::load();
        $this->evaluator = new PathwayEvaluator($kb);
    }

    public function testRiboflavinPathwayEvaluation(): void
    {
        // Provide GTP cyclohydrolase II (K01497) and DHBP synthase (K02858)
        $matchedKos = [
            'K01497' => ['ko' => 'K01497', 'q' => 0.8, 'tokens' => [], 'warnings' => []],
            'K02858' => ['ko' => 'K02858', 'q' => 0.8, 'tokens' => [], 'warnings' => []],
        ];

        $eval = $this->evaluator->evaluateVitamin('B2', $matchedKos);
        $best = $eval['best_pathway'];

        $this->assertSame('B2_DENOVO', $best['code']);
        // B2_DENOVO has 6 evaluated steps (excluding gap B2.PHOS)
        // 2 present (K01497: key=2, K02858: key=2 -> total present weight = 4)
        // Total weight = 2 (GCH2) + 1 (DEAM) + 1 (RED) + 2 (DHBPS) + 2 (LS) + 2 (RS) = 10
        // C_w = 4 / 10 = 0.40
        $this->assertSame(0.40, $best['completeness']['weighted']);
        // 2 / 6 = 0.3333
        $this->assertSame(0.3333, $best['completeness']['unweighted']);
        // 2 key present out of 4 key total -> KC = 0.5
        $this->assertSame(0.5, $best['completeness']['key_coverage']);
    }

    public function testComplexEvaluation(): void
    {
        // Test B1_TYR step B1.THISF which requires K03148+K03154
        // Only 1 subunit present -> partial
        $partialKos = [
            'K03148' => ['ko' => 'K03148', 'q' => 0.8, 'tokens' => [], 'warnings' => []],
        ];
        $evalPartial = $this->evaluator->evaluateVitamin('B1', $partialKos);
        $bestPartial = $evalPartial['best_pathway'];
        $stepThisF = null;
        foreach ($bestPartial['steps'] as $s) {
            if ($s['code'] === 'B1.THISF') {
                $stepThisF = $s;
                break;
            }
        }
        $this->assertNotNull($stepThisF);
        $this->assertSame('partial', $stepThisF['status']);
        $this->assertSame(0.5, $stepThisF['score']);

        // Both subunits present -> present
        $fullKos = [
            'K03148' => ['ko' => 'K03148', 'q' => 0.8, 'tokens' => [], 'warnings' => []],
            'K03154' => ['ko' => 'K03154', 'q' => 0.8, 'tokens' => [], 'warnings' => []],
        ];
        $evalFull = $this->evaluator->evaluateVitamin('B1', $fullKos);
        $bestFull = $evalFull['best_pathway'];
        foreach ($bestFull['steps'] as $s) {
            if ($s['code'] === 'B1.THISF') {
                $this->assertSame('present', $s['status']);
                $this->assertSame(1.0, $s['score']);
            }
        }
    }

    public function testFusionEnzymeCoversMultipleSteps(): void
    {
        // RibBA (K14652) is a bifunctional enzyme covering B2.GCH2 and B2.DHBPS
        $matchedKos = [
            'K14652' => ['ko' => 'K14652', 'q' => 0.8, 'tokens' => [], 'warnings' => []],
        ];

        $eval = $this->evaluator->evaluateVitamin('B2', $matchedKos);
        $best = $eval['best_pathway'];

        $presentSteps = $best['present_steps'];
        $this->assertTrue(in_array('B2.GCH2', $presentSteps, true));
        $this->assertTrue(in_array('B2.DHBPS', $presentSteps, true));
    }
}
