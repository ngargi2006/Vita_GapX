<?php

declare(strict_types=1);

namespace VitaGap\Input\Parsers;

use VitaGap\Input\Tokenizer;

final class PlainListParser implements ParserInterface
{
    public function __construct(
        private readonly Tokenizer $tokenizer = new Tokenizer()
    ) {
    }

    public function parse(string $content): array
    {
        return $this->tokenizer->tokenize($content, defaultQuality: 0.8);
    }
}
