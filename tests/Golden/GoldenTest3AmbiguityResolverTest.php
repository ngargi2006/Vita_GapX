<?php

declare(strict_types=1);

namespace VitaGap\Tests\Golden;

use VitaGap\Engine\Analyzer;
use VitaGap\Tests\TestCase;

final class GoldenTest3AmbiguityResolverTest extends TestCase
{
    private Analyzer $analyzer;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer();
    }

    public function testRibCIsAmbiguousUnderGenericNaming(): void
    {
        $report = $this->analyzer->analyze([
            'organism' => 'Unknown Bacterium',
            'naming' => 'generic',
            'data' => 'ribC',
            'vitamins' => ['B2'],
        ]);

        $ambiguities = $report['ambiguities'];
        $this->assertCount(1, $ambiguities, 'ribC should be marked ambiguous under generic naming');
        $this->assertSame('ribc', $ambiguities[0]['symbol']);

        $options = $ambiguities[0]['options'];
        $this->assertCount(2, $options);
        $kos = array_column($options, 'ko');
        $this->assertTrue(in_array('K00793', $kos, true));
        $this->assertTrue(in_array('K11753', $kos, true));
    }

    public function testRibCResolvesUnderEcoliNaming(): void
    {
        $report = $this->analyzer->analyze([
            'organism' => 'Escherichia coli strain',
            'naming' => 'ecoli',
            'data' => 'ribC',
            'vitamins' => ['B2'],
        ]);

        $this->assertCount(0, $report['ambiguities'], 'No ambiguities should remain under ecoli naming');
        $b2Steps = $report['vitamins']['B2']['q1_detected'];
        $stepCodes = array_column($b2Steps, 'code');
        // K00793 is riboflavin synthase (B2.RS)
        $this->assertTrue(in_array('B2.RS', $stepCodes, true));
    }

    public function testRibCResolvesUnderBsubtilisNaming(): void
    {
        $report = $this->analyzer->analyze([
            'organism' => 'Bacillus subtilis strain',
            'naming' => 'bsubtilis',
            'data' => 'ribC',
            'vitamins' => ['B2'],
        ]);

        $this->assertCount(0, $report['ambiguities'], 'No ambiguities should remain under bsubtilis naming');
        // In B. subtilis, ribC is K11753 (riboflavin kinase / FAD synthetase in B2_ACTIVATION)
        $activation = $report['vitamins']['B2']['activation'];
        $this->assertCount(1, $activation);
        $actSteps = $activation[0]['steps'];
        $rfkStep = null;
        foreach ($actSteps as $s) {
            if ($s['code'] === 'B2.RFK') {
                $rfkStep = $s;
                break;
            }
        }
        $this->assertNotNull($rfkStep);
        $this->assertSame('present', $rfkStep['status']);
    }
}
