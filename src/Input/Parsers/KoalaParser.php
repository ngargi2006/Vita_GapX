<?php

declare(strict_types=1);

namespace VitaGap\Input\Parsers;

use VitaGap\Input\Token;

final class KoalaParser implements ParserInterface
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

            $parts = preg_split('/\t+/', $line);
            if ($parts === false || count($parts) < 2) {
                // Try splitting by general whitespace
                $parts = preg_split('/\s+/', $line);
            }

            if ($parts === false || count($parts) < 2) {
                continue;
            }

            $gene = trim($parts[0]);
            $ko = trim($parts[1]);

            if (preg_match('/^K\d{5}$/i', $ko)) {
                $norm = strtoupper($ko);
                if (!isset($seen[$norm])) {
                    $seen[$norm] = true;
                    $tokens[] = new Token(
                        raw: $ko,
                        type: Token::TYPE_KO,
                        value: $norm,
                        quality: 0.9,
                        geneId: $gene,
                        metadata: ['source' => 'koala']
                    );
                }
            }
        }

        return $tokens;
    }
}
