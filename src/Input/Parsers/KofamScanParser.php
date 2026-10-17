<?php

declare(strict_types=1);

namespace VitaGap\Input\Parsers;

use VitaGap\Input\Token;

final class KofamScanParser implements ParserInterface
{
    public function parse(string $content): array
    {
        $tokens = [];
        $seen = [];
        $lines = preg_split('/[\r\n]+/', $content);
        if ($lines === false) {
            return [];
        }

        $isDetailFormat = false;
        foreach ($lines as $line) {
            if (str_starts_with($line, '#') && (str_contains($line, 'score') || str_contains($line, 'thrshld') || str_contains($line, 'threshold'))) {
                $isDetailFormat = true;
                break;
            }
            if (str_starts_with($line, '*')) {
                $isDetailFormat = true;
                break;
            }
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if ($isDetailFormat || str_starts_with($line, '*')) {
                // Detail format: only lines with leading '*' indicate above-threshold hit
                if (!str_starts_with($line, '*')) {
                    continue;
                }

                $parts = preg_split('/\s+/', ltrim($line, '* '));
                if ($parts === false || count($parts) < 2) {
                    continue;
                }

                $gene = $parts[0];
                $ko = $parts[1];

                if (preg_match('/^K\d{5}$/i', $ko)) {
                    $norm = strtoupper($ko);
                    if (!isset($seen[$norm])) {
                        $seen[$norm] = true;
                        $tokens[] = new Token(
                            raw: $ko,
                            type: Token::TYPE_KO,
                            value: $norm,
                            quality: 1.0,
                            geneId: $gene,
                            metadata: ['source' => 'kofamscan_detail']
                        );
                    }
                }
            } else {
                // Mapper format: tab-delimited or whitespace (gene \t KO)
                $parts = preg_split('/\s+/', $line);
                if ($parts === false || count($parts) < 2) {
                    continue;
                }

                $gene = $parts[0];
                $ko = $parts[1];

                if (preg_match('/^K\d{5}$/i', $ko)) {
                    $norm = strtoupper($ko);
                    if (!isset($seen[$norm])) {
                        $seen[$norm] = true;
                        $tokens[] = new Token(
                            raw: $ko,
                            type: Token::TYPE_KO,
                            value: $norm,
                            quality: 1.0,
                            geneId: $gene,
                            metadata: ['source' => 'kofamscan_mapper']
                        );
                    }
                }
            }
        }

        return $tokens;
    }
}
