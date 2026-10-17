<?php

declare(strict_types=1);

namespace VitaGap\Input\Parsers;

use VitaGap\Input\Token;

final class ProkkaParser implements ParserInterface
{
    public function parse(string $content): array
    {
        $tokens = [];
        $seen = [];
        $lines = preg_split('/[\r\n]+/', $content);
        if ($lines === false) {
            return [];
        }

        $geneIdx = null;
        $ecIdx = null;
        $locusIdx = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $cols = preg_split('/\t+/', $line);
            if ($cols === false) {
                continue;
            }

            // Detect header line
            if ($geneIdx === null && (in_array('gene', $cols, true) || in_array('locus_tag', $cols, true))) {
                foreach ($cols as $idx => $col) {
                    $c = strtolower(trim($col));
                    if ($c === 'gene') {
                        $geneIdx = $idx;
                    } elseif ($c === 'ec_number' || $c === 'ec') {
                        $ecIdx = $idx;
                    } elseif ($c === 'locus_tag') {
                        $locusIdx = $idx;
                    }
                }
                continue;
            }

            $locusTag = ($locusIdx !== null && isset($cols[$locusIdx])) ? trim($cols[$locusIdx]) : null;

            // Extract gene symbol
            if ($geneIdx !== null && isset($cols[$geneIdx])) {
                $gene = trim($cols[$geneIdx]);
                if ($gene !== '' && $gene !== '-') {
                    $normGene = strtolower($gene);
                    if (!isset($seen['SYMBOL:' . $normGene])) {
                        $seen['SYMBOL:' . $normGene] = true;
                        $tokens[] = new Token(
                            raw: $gene,
                            type: Token::TYPE_SYMBOL,
                            value: $normGene,
                            quality: 0.8,
                            geneId: $locusTag,
                            metadata: ['source' => 'prokka']
                        );
                    }
                }
            }

            // Extract EC number
            if ($ecIdx !== null && isset($cols[$ecIdx])) {
                $ec = trim($cols[$ecIdx]);
                if ($ec !== '' && $ec !== '-') {
                    if (preg_match('/^(?:EC:?)?(\d+\.\d+\.\d+\.\d+)$/i', $ec, $matches)) {
                        $ecVal = $matches[1];
                        if (!isset($seen['EC:' . $ecVal])) {
                            $seen['EC:' . $ecVal] = true;
                            $tokens[] = new Token(
                                raw: $ec,
                                type: Token::TYPE_EC,
                                value: $ecVal,
                                quality: 0.8,
                                geneId: $locusTag,
                                metadata: ['source' => 'prokka']
                            );
                        }
                    }
                }
            }
        }

        return $tokens;
    }
}
