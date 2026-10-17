<?php

declare(strict_types=1);

namespace VitaGap\Tests\Unit;

use VitaGap\Engine\EvidenceScorer;
use VitaGap\Engine\PathwayEvaluator;
use VitaGap\Engine\RuleEngine;
use VitaGap\Kb\KbCache;
use VitaGap\Tests\TestCase;

final class EvidenceScorerTest extends TestCase
{
    private EvidenceScorer $scorer;
    private PathwayEvaluator $evaluator;

    public function setUp(): void
    {
        $kb = KbCache::load();
        $this->scorer = new EvidenceScorer();
        $this->evaluator = new PathwayEvaluator($kb);
    }

    public function testBlockedInOpenWorldGivesZeroEvidence(): void
    {
        // Provide partial B2
        $matchedKos = [
            'K01497' => ['ko' => 'K01497', 'q' => 0.8, 'tokens' => [], 'warnings' => []],
            'K02858' => ['ko' => 'K02858', 'q' => 0.8, 'tokens' => [], 'warnings' => []],
        ];

        $eval = $this->evaluator->evaluateVitamin('B2', $matchedKos);
        $best = $eval['best_pathway'];

        $result = $this->scorer->score(
            RuleEngine::CALL_BLOCKED,
            $best,
            ['scope' => 'open', 'genome_type' => 'unknown', 'completeness' => null]
        );

        $this->assertSame(0, $result['score']);
        $this->assertSame(EvidenceScorer::TIER_WEAK, $result['tier']);
    }

    public function testBlockedInClosedWorldCompleteGenome(): void
    {
        $matchedKos = [
            'K01497' => ['ko' => 'K01497', 'q' => 0.8, 'tokens' => [], 'warnings' => []],
            'K02858' => ['ko' => 'K02858', 'q' => 0.8, 'tokens' => [], 'warnings' => []],
        ];

        $eval = $this->evaluator->evaluateVitamin('B2', $matchedKos);
        $best = $eval['best_pathway'];

        $result = $this->scorer->score(
            RuleEngine::CALL_BLOCKED,
            $best,
            ['scope' => 'closed', 'genome_type' => 'complete', 'completeness' => 100.0]
        );

        // Score = 100 * E_found (0.8) * a (1.0) = 80
        $this->assertSame(80, $result['score']);
        $this->assertSame(EvidenceScorer::TIER_STRONG, $result['tier']);
    }

    public function testPrototrophEvidenceScore(): void
    {
        // All B2 steps present with q = 0.9
        $matchedKos = [
            'K01497' => ['ko' => 'K01497', 'q' => 0.9, 'tokens' => [], 'warnings' => []],
            'K01498' => ['ko' => 'K01498', 'q' => 0.9, 'tokens' => [], 'warnings' => []],
            'K00082' => ['ko' => 'K00082', 'q' => 0.9, 'tokens' => [], 'warnings' => []],
            'K02858' => ['ko' => 'K02858', 'q' => 0.9, 'tokens' => [], 'warnings' => []],
            'K00794' => ['ko' => 'K00794', 'q' => 0.9, 'tokens' => [], 'warnings' => []],
            'K00793' => ['ko' => 'K00793', 'q' => 0.9, 'tokens' => [], 'warnings' => []],
        ];

        $eval = $this->evaluator->evaluateVitamin('B2', $matchedKos);
        $best = $eval['best_pathway'];

        $result = $this->scorer->score(
            RuleEngine::CALL_PROTOTROPH,
            $best,
            ['scope' => 'closed', 'genome_type' => 'complete', 'completeness' => 100.0]
        );

        // C_w = 1.0, E_found = 0.9 -> score = 100 * 0.9 * 1.0 = 90
        $this->assertSame(90, $result['score']);
        $this->assertSame(EvidenceScorer::TIER_STRONG, $result['tier']);
    }

    public function testTransporterBonusForAuxotroph(): void
    {
        $eval = $this->evaluator->evaluateVitamin('B2', []);
        $best = $eval['best_pathway'];

        $noTransporter = $this->scorer->score(
            RuleEngine::CALL_AUXOTROPH,
            $best,
            ['scope' => 'closed', 'genome_type' => 'draft', 'completeness' => null],
            transporterFound: false
        );

        $withTransporter = $this->scorer->score(
            RuleEngine::CALL_AUXOTROPH,
            $best,
            ['scope' => 'closed', 'genome_type' => 'draft', 'completeness' => null],
            transporterFound: true
        );

        $this->assertSame($noTransporter['score'] + 10, $withTransporter['score']);
    }
}
