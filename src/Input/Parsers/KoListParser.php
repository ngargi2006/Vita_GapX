<?php

declare(strict_types=1);

namespace VitaGap\Input\Parsers;

use VitaGap\Input\Token;

final class KoListParser implements ParserInterface
{
    public function parse(string $content): array
    {
        $tokens = [];
        $seen = [];
        $lines = preg_split('/[\r\n]+/', $content);
        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Extract any K-numbers in the line
            if (preg_match_all('/(?:ko:)?(K\d{5})/i', $line, $matches)) {
                foreach ($matches[1] as $ko) {
                    $norm = strtoupper($ko);
                    if (!isset($seen[$norm])) {
                        $seen[$norm] = true;
                        $tokens[] = new Token(
                            raw: $ko,
                            type: Token::TYPE_KO,
                            value: $norm,
                            quality: 0.9
                        );
                    }
                }
            }
        }

        return $tokens;
    }
}
