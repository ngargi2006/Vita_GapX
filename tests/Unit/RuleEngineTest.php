<?php

declare(strict_types=1);

namespace VitaGap\Tests\Unit;

use VitaGap\Engine\PathwayEvaluator;
use VitaGap\Engine\RuleEngine;
use VitaGap\Kb\KbCache;
use VitaGap\Tests\TestCase;

final class RuleEngineTest extends TestCase
{
    private RuleEngine $ruleEngine;
    private PathwayEvaluator $evaluator;
    private array $kb;

    public function setUp(): void
    {
        $this->kb = KbCache::load();
        $this->ruleEngine = new RuleEngine();
        $this->evaluator = new PathwayEvaluator($this->kb);
    }

    public function testNotAssessableWhenEmptyInOpenWorld(): void
    {
        $vEval = $this->evaluator->evaluateVitamin('B2', []);
        $vDef = $this->getVitaminDef('B2');

        $result = $this->ruleEngine->evaluate($vDef, $vEval, [
            'scope' => 'open',
            'genome_type' => 'unknown',
            'completeness' => null,
        ]);

        $this->assertSame(RuleEngine::CALL_NOT_ASSESSABLE, $result['code']);
    }

    public function testAuxotrophInClosedWorld(): void
    {
        $vEval = $this->evaluator->evaluateVitamin('B2', []);
        $vDef = $this->getVitaminDef('B2');

        $result = $this->ruleEngine->evaluate($vDef, $vEval, [
            'scope' => 'closed',
            'genome_type' => 'complete',
            'completeness' => 100.0,
        ]);

        $this->assertSame(RuleEngine::CALL_AUXOTROPH, $result['code']);
    }

    public function testPrecursorDependentB1(): void
    {
        // ThiC (B1.THIC) absent, but ThiG (B1.THIG) and ThiE (B1.THIE) present -> HMP-dependent
        $matchedKos = [
            'K03149' => ['ko' => 'K03149', 'q' => 0.9, 'tokens' => [], 'warnings' => []], // ThiG
            'K00788' => ['ko' => 'K00788', 'q' => 0.9, 'tokens' => [], 'warnings' => []], // ThiE
        ];

        $vEval = $this->evaluator->evaluateVitamin('B1', $matchedKos);
        $vDef = $this->getVitaminDef('B1');

        $result = $this->ruleEngine->evaluate($vDef, $vEval, [
            'scope' => 'closed',
            'genome_type' => 'complete',
            'completeness' => 100.0,
        ]);

        $this->assertSame(RuleEngine::CALL_PRECURSOR_DEPENDENT, $result['code']);
    }

    private function getVitaminDef(string $code): array
    {
        foreach ($this->kb['vitamins'] as $v) {
            if ($v['code'] === $code) {
                return $v;
            }
        }
        throw new \RuntimeException("Vitamin {$code} not found.");
    }
}
