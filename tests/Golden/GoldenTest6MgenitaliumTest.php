<?php

declare(strict_types=1);

namespace VitaGap\Tests\Golden;

use VitaGap\Engine\Analyzer;
use VitaGap\Engine\RuleEngine;
use VitaGap\Tests\TestCase;

final class GoldenTest6MgenitaliumTest extends TestCase
{
    private Analyzer $analyzer;
    private string $koData;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer();
        $fixture = __DIR__ . '/../fixtures/mge.ko';
        $this->koData = file_get_contents($fixture) ?: '';
    }

    public function testMgenitaliumIsAuxotrophic(): void
    {
        $report = $this->analyzer->analyze([
            'organism' => 'Mycoplasma genitalium G37',
            'naming' => 'generic',
            'genome_type' => 'complete',
            'completeness' => 100.0,
            'scope' => 'closed',
            'format' => 'ko_list',
            'data' => $this->koData,
        ]);

        $vitamins = $report['vitamins'];

        // M. genitalium must not be a prototroph for any vitamin
        foreach ($vitamins as $vCode => $vData) {
            $call = $vData['q4_call']['code'];
            $this->assertTrue(
                $call !== RuleEngine::CALL_PROTOTROPH,
                "M. genitalium must not be a prototroph for {$vCode}"
            );
            $this->assertTrue(
                $call !== RuleEngine::CALL_PROBABLE_PROTOTROPH,
                "M. genitalium must not be a probable prototroph for {$vCode}"
            );
            $this->assertTrue(
                in_array($call, [RuleEngine::CALL_AUXOTROPH, RuleEngine::CALL_NOT_REQUIRED], true),
                "M. genitalium should be AUXOTROPH or NOT_REQUIRED for {$vCode}, got {$call}"
            );
        }
    }
}
