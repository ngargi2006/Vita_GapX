<?php

declare(strict_types=1);

namespace VitaGap\Tests\Golden;

use VitaGap\Engine\Analyzer;
use VitaGap\Engine\RuleEngine;
use VitaGap\Tests\TestCase;

final class GoldenTest5BsubtilisTest extends TestCase
{
    private Analyzer $analyzer;
    private string $koData;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer();
        $fixture = __DIR__ . '/../fixtures/bsu.ko';
        $this->koData = file_get_contents($fixture) ?: '';
    }

    public function testBsubtilisBiosyntheticPhenotype(): void
    {
        $report = $this->analyzer->analyze([
            'organism' => 'Bacillus subtilis subsp. subtilis str. 168',
            'naming' => 'bsubtilis',
            'genome_type' => 'complete',
            'completeness' => 100.0,
            'scope' => 'closed',
            'format' => 'ko_list',
            'data' => $this->koData,
        ]);

        $vitamins = $report['vitamins'];

        // 1. B2 (Riboflavin): Must be PROTOTROPH
        // B. subtilis uses the bifunctional RibBA (K14652) covering both GCH2 and DHBPS
        $b2 = $vitamins['B2'];
        $this->assertSame(RuleEngine::CALL_PROTOTROPH, $b2['q4_call']['code'], 'B. subtilis must be a B2 prototroph');
        $this->assertSame(100.0, $b2['q3_completeness']['weighted_pct']);

        // 2. B6 (PLP): Must be PROTOTROPH via DXP-independent pathway (B6_DXP_INDEP)
        $b6 = $vitamins['B6'];
        $this->assertSame(RuleEngine::CALL_PROTOTROPH, $b6['q4_call']['code'], 'B. subtilis must be a B6 prototroph via PdxST');
        $this->assertSame('B6_DXP_INDEP', $b6['best_pathway']['code']);

        // 3. K2 (Menaquinone): Must be PROTOTROPH
        $k2 = $vitamins['K2'];
        $this->assertSame(RuleEngine::CALL_PROTOTROPH, $k2['q4_call']['code'], 'B. subtilis must be a K2 prototroph');
    }
}
