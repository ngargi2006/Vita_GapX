<?php

declare(strict_types=1);

namespace VitaGap\Tests\Unit;

use VitaGap\Engine\Matcher;
use VitaGap\Input\Token;
use VitaGap\Kb\KbCache;
use VitaGap\Tests\TestCase;

final class MatcherTest extends TestCase
{
    private Matcher $matcher;

    public function setUp(): void
    {
        $kb = KbCache::load();
        $this->matcher = new Matcher($kb);
    }

    public function testDirectKoMatching(): void
    {
        $token = new Token('K01497', Token::TYPE_KO, 'K01497', 0.9);
        $result = $this->matcher->match([$token]);

        $this->assertArrayHasKey('K01497', $result['matched_kos']);
        $this->assertSame(0.9, $result['matched_kos']['K01497']['q']);
        $this->assertCount(0, $result['ambiguities']);
    }

    public function testUnambiguousSymbolMatching(): void
    {
        $token = new Token('ribB', Token::TYPE_SYMBOL, 'ribb', 0.8);
        $result = $this->matcher->match([$token]);

        $this->assertArrayHasKey('K02858', $result['matched_kos']);
        $this->assertSame(0.8, $result['matched_kos']['K02858']['q']);
    }

    public function testParalogWarning(): void
    {
        $token = new Token('panE', Token::TYPE_SYMBOL, 'pane', 0.8);
        $result = $this->matcher->match([$token]);

        $this->assertArrayHasKey('K00077', $result['matched_kos']);
        $this->assertSame(0.6, $result['matched_kos']['K00077']['q']);
        $this->assertStringContainsString('paralog', $result['matched_kos']['K00077']['warnings'][0]);
    }

    public function testAmbiguityDetection(): void
    {
        $token = new Token('ribC', Token::TYPE_SYMBOL, 'ribc', 0.8);
        $result = $this->matcher->match([$token], namingConvention: 'generic');

        $this->assertCount(1, $result['ambiguities']);
        $this->assertSame('ribc', $result['ambiguities'][0]['symbol']);
        $this->assertCount(2, $result['ambiguities'][0]['options']);
    }

    public function testAmbiguityResolutionWithManualOverride(): void
    {
        $token = new Token('ribC', Token::TYPE_SYMBOL, 'ribc', 0.8);
        $result = $this->matcher->match(
            [$token],
            namingConvention: 'generic',
            resolutions: ['ribc' => 'K00793']
        );

        $this->assertCount(0, $result['ambiguities']);
        $this->assertArrayHasKey('K00793', $result['matched_kos']);
    }
}
