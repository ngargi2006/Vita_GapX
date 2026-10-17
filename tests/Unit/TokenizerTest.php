<?php

declare(strict_types=1);

namespace VitaGap\Tests\Unit;

use VitaGap\Input\Token;
use VitaGap\Input\Tokenizer;
use VitaGap\Tests\TestCase;

final class TokenizerTest extends TestCase
{
    private Tokenizer $tokenizer;

    public function setUp(): void
    {
        $this->tokenizer = new Tokenizer();
    }

    public function testTokenizeWithArrows(): void
    {
        $input = "RibA -> RibB => RibD \u{2192} RibH \u{27F6} RibE";
        $tokens = $this->tokenizer->tokenize($input);

        $this->assertCount(5, $tokens);
        $this->assertSame('riba', $tokens[0]->value);
        $this->assertSame('ribb', $tokens[1]->value);
        $this->assertSame('ribd', $tokens[2]->value);
        $this->assertSame('ribh', $tokens[3]->value);
        $this->assertSame('ribe', $tokens[4]->value);
    }

    public function testTokenizeMixedDelimiters(): void
    {
        $input = "RibA, RibB;\tRibD\nRibH ribE";
        $tokens = $this->tokenizer->tokenize($input);

        $this->assertCount(5, $tokens);
        $values = array_map(static fn(Token $t) => $t->value, $tokens);
        $this->assertSame(['riba', 'ribb', 'ribd', 'ribh', 'ribe'], $values);
    }

    public function testClassifyTokenTypes(): void
    {
        $ko = $this->tokenizer->classify('K01497');
        $this->assertSame(Token::TYPE_KO, $ko->type);
        $this->assertSame('K01497', $ko->value);

        $koPrefixed = $this->tokenizer->classify('ko:K00793');
        $this->assertSame(Token::TYPE_KO, $koPrefixed->type);
        $this->assertSame('K00793', $koPrefixed->value);

        $ec = $this->tokenizer->classify('EC:3.5.4.25');
        $this->assertSame(Token::TYPE_EC, $ec->type);
        $this->assertSame('3.5.4.25', $ec->value);

        $symbol = $this->tokenizer->classify('RibBA');
        $this->assertSame(Token::TYPE_SYMBOL, $symbol->type);
        $this->assertSame('ribba', $symbol->value);
    }
}
