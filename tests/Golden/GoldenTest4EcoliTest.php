<?php

declare(strict_types=1);

namespace VitaGap\Tests\Golden;

use VitaGap\Engine\Analyzer;
use VitaGap\Engine\RuleEngine;
use VitaGap\Tests\TestCase;

final class GoldenTest4EcoliTest extends TestCase
{
    private Analyzer $analyzer;
    private string $koData;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer();
        $fixture = __DIR__ . '/../fixtures/eco.ko';
        $this->koData = file_get_contents($fixture) ?: '';
    }

    public function testEcoliK12BiosyntheticPhenotype(): void
    {
        $report = $this->analyzer->analyze([
            'organism' => 'Escherichia coli str. K-12 substr. MG1655',
            'naming' => 'ecoli',
            'genome_type' => 'complete',
            'completeness' => 100.0,
            'scope' => 'closed',
            'format' => 'ko_list',
            'data' => $this->koData,
        ]);

        $vitamins = $report['vitamins'];

        // 1. B2 (Riboflavin): Must be PROTOTROPH
        $b2 = $vitamins['B2'];
        $this->assertSame(RuleEngine::CALL_PROTOTROPH, $b2['q4_call']['code'], 'E. coli must be a B2 prototroph');
        $this->assertSame(100.0, $b2['q3_completeness']['weighted_pct']);
        $this->assertSame(100.0, $b2['q3_completeness']['key_coverage_pct']);

        // 2. B1 (Thiamine): Must be PROTOTROPH (via ThiH tyrosine route)
        $b1 = $vitamins['B1'];
        $this->assertSame(RuleEngine::CALL_PROTOTROPH, $b1['q4_call']['code'], 'E. coli must be a B1 prototroph');

        // 3. B6 (PLP): Must be PROTOTROPH (via DXP-dependent route)
        $b6 = $vitamins['B6'];
        $this->assertSame(RuleEngine::CALL_PROTOTROPH, $b6['q4_call']['code'], 'E. coli must be a B6 prototroph');

        // 4. B7 (Biotin): Must be PROTOTROPH (via BioC-BioH route)
        $b7 = $vitamins['B7'];
        $this->assertSame(RuleEngine::CALL_PROTOTROPH, $b7['q4_call']['code'], 'E. coli must be a B7 prototroph');

        // 5. B9 (Folate): Must be PROTOTROPH
        $b9 = $vitamins['B9'];
        $this->assertSame(RuleEngine::CALL_PROTOTROPH, $b9['q4_call']['code'], 'E. coli must be a B9 prototroph');

        // 6. K2 (Menaquinone): Must be PROTOTROPH
        $k2 = $vitamins['K2'];
        $this->assertSame(RuleEngine::CALL_PROTOTROPH, $k2['q4_call']['code'], 'E. coli must be a K2 prototroph');

        // 7. B12 (Cobalamin): MUST NOT be de novo PROTOTROPH!
        // E. coli cannot make the corrin ring de novo
        $b12 = $vitamins['B12'];
        $this->assertTrue(
            $b12['q4_call']['code'] !== RuleEngine::CALL_PROTOTROPH,
            'E. coli must NOT be classified as a de novo B12 prototroph'
        );
        $this->assertTrue(
            in_array($b12['q4_call']['code'], [RuleEngine::CALL_PRECURSOR_DEPENDENT, RuleEngine::CALL_AUXOTROPH], true),
            'E. coli should be PRECURSOR_DEPENDENT (cobinamide salvager) or AUXOTROPH'
        );
    }
}
