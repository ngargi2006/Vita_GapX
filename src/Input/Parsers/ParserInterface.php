<?php

declare(strict_types=1);

namespace VitaGap\Input\Parsers;

use VitaGap\Input\Token;

interface ParserInterface
{
    /**
     * Parse raw content into an array of Token instances.
     *
     * @return array<Token>
     */
    public function parse(string $content): array;
}
