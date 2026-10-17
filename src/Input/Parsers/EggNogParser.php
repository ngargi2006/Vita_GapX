<?php

declare(strict_types=1);

namespace VitaGap\Input\Parsers;

use VitaGap\Input\Token;

final class EggNogParser implements ParserInterface
{
    public function parse(string $content): array
    {
        $tokens = [];
        $seen = [];
        $lines = preg_split('/[\r\n]+/', $content);
        if ($lines === false) {
            return [];
        }

        $colIndices = [
            'query' => 0,
            'preferred_name' => null,
            'ec' => null,
            'kegg_ko' => null,
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Look for header row to identify column positions
            if (str_starts_with($line, '#')) {
                if (str_contains($line, 'Preferred_name') || str_contains($line, 'KEGG_ko') || str_contains($line, 'seed_ortholog')) {
                    $headerCols = preg_split('/\t+/', ltrim($line, '# '));
                    if ($headerCols !== false) {
                        foreach ($headerCols as $idx => $name) {
                            $nameClean = trim($name);
                            if (strcasecmp($nameClean, 'query') === 0 || strcasecmp($nameClean, 'query_name') === 0) {
                                $colIndices['query'] = $idx;
                            } elseif (strcasecmp($nameClean, 'Preferred_name') === 0) {
                                $colIndices['preferred_name'] = $idx;
                            } elseif (strcasecmp($nameClean, 'EC') === 0) {
                                $colIndices['ec'] = $idx;
                            } elseif (strcasecmp($nameClean, 'KEGG_ko') === 0) {
                                $colIndices['kegg_ko'] = $idx;
                            }
                        }
                    }
                }
                continue;
            }

            $cols = preg_split('/\t+/', $line);
            if ($cols === false) {
                continue;
            }

            $query = isset($colIndices['query'], $cols[$colIndices['query']]) ? trim($cols[$colIndices['query']]) : null;

            // Extract KEGG KOs
            $koCol = $colIndices['kegg_ko'] !== null && isset($cols[$colIndices['kegg_ko']])
                ? $cols[$colIndices['kegg_ko']]
                : null;

            if ($koCol !== null && $koCol !== '-' && $koCol !== '') {
                if (preg_match_all('/(?:ko:)?(K\d{5})/i', $koCol, $matches)) {
                    foreach ($matches[1] as $ko) {
                        $norm = strtoupper($ko);
                        if (!isset($seen['KO:' . $norm])) {
                            $seen['KO:' . $norm] = true;
                            $tokens[] = new Token(
                                raw: $ko,
                                type: Token::TYPE_KO,
                                value: $norm,
                                quality: 0.9,
                                geneId: $query,
                                metadata: ['source' => 'eggnog']
                            );
                        }
                    }
                }
            }

            // Extract Preferred Name (symbol)
            $nameCol = $colIndices['preferred_name'] !== null && isset($cols[$colIndices['preferred_name']])
                ? trim($cols[$colIndices['preferred_name']])
                : null;

            if ($nameCol !== null && $nameCol !== '-' && $nameCol !== '') {
                $normName = strtolower($nameCol);
                if (!isset($seen['SYMBOL:' . $normName])) {
                    $seen['SYMBOL:' . $normName] = true;
                    $tokens[] = new Token(
                        raw: $nameCol,
                        type: Token::TYPE_SYMBOL,
                        value: $normName,
                        quality: 0.8,
                        geneId: $query,
                        metadata: ['source' => 'eggnog']
                    );
                }
            }

            // Extract EC
            $ecCol = $colIndices['ec'] !== null && isset($cols[$colIndices['ec']])
                ? trim($cols[$colIndices['ec']])
                : null;

            if ($ecCol !== null && $ecCol !== '-' && $ecCol !== '') {
                $ecs = preg_split('/[,;\s]+/', $ecCol);
                if ($ecs !== false) {
                    foreach ($ecs as $ec) {
                        $ec = trim($ec);
                        if (preg_match('/^(?:EC:?)?(\d+\.\d+\.\d+\.\d+)$/i', $ec, $ecm)) {
                            $ecVal = $ecm[1];
                            if (!isset($seen['EC:' . $ecVal])) {
                                $seen['EC:' . $ecVal] = true;
                                $tokens[] = new Token(
                                    raw: $ec,
                                    type: Token::TYPE_EC,
                                    value: $ecVal,
                                    quality: 0.8,
                                    geneId: $query,
                                    metadata: ['source' => 'eggnog']
                                );
                            }
                        }
                    }
                }
            }
        }

        return $tokens;
    }
}
