<?php

declare(strict_types=1);

namespace VitaGap\Input\Parsers;

use VitaGap\Input\Token;

final class GenericCsvParser implements ParserInterface
{
    public function parse(string $content): array
    {
        $tokens = [];
        $seen = [];
        $lines = preg_split('/[\r\n]+/', trim($content));
        if ($lines === false || count($lines) === 0) {
            return [];
        }

        // Determine delimiter (, or \t or ;)
        $firstLine = $lines[0];
        $delimiter = ',';
        if (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        } elseif (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        }

        $headers = str_getcsv($firstLine, $delimiter, '"', "\\");
        $koIdx = null;
        $geneIdx = null;
        $ecIdx = null;

        foreach ($headers as $idx => $header) {
            $h = strtolower(trim($header));
            if (in_array($h, ['ko', 'kegg', 'kegg_ko', 'ko_id', 'orthology'], true)) {
                $koIdx = $idx;
            } elseif (in_array($h, ['gene', 'symbol', 'gene_name', 'name', 'locus_tag'], true)) {
                $geneIdx = $idx;
            } elseif (in_array($h, ['ec', 'ec_number', 'ec_num'], true)) {
                $ecIdx = $idx;
            }
        }

        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $row = str_getcsv($line, $delimiter, '"', "\\");
            if ($koIdx !== null && isset($row[$koIdx])) {
                $koRaw = trim($row[$koIdx]);
                if (preg_match_all('/(?:ko:)?(K\d{5})/i', $koRaw, $matches)) {
                    foreach ($matches[1] as $ko) {
                        $norm = strtoupper($ko);
                        if (!isset($seen['KO:' . $norm])) {
                            $seen['KO:' . $norm] = true;
                            $tokens[] = new Token(
                                raw: $ko,
                                type: Token::TYPE_KO,
                                value: $norm,
                                quality: 0.9,
                                metadata: ['source' => 'csv']
                            );
                        }
                    }
                }
            }

            if ($geneIdx !== null && isset($row[$geneIdx])) {
                $gene = trim($row[$geneIdx]);
                if ($gene !== '' && $gene !== '-') {
                    $normGene = strtolower($gene);
                    if (!isset($seen['SYMBOL:' . $normGene])) {
                        $seen['SYMBOL:' . $normGene] = true;
                        $tokens[] = new Token(
                            raw: $gene,
                            type: Token::TYPE_SYMBOL,
                            value: $normGene,
                            quality: 0.8,
                            metadata: ['source' => 'csv']
                        );
                    }
                }
            }

            if ($ecIdx !== null && isset($row[$ecIdx])) {
                $ecRaw = trim($row[$ecIdx]);
                if (preg_match('/^(?:EC:?)?(\d+\.\d+\.\d+\.\d+)$/i', $ecRaw, $matches)) {
                    $ec = $matches[1];
                    if (!isset($seen['EC:' . $ec])) {
                        $seen['EC:' . $ec] = true;
                        $tokens[] = new Token(
                            raw: $ecRaw,
                            type: Token::TYPE_EC,
                            value: $ec,
                            quality: 0.8,
                            metadata: ['source' => 'csv']
                        );
                    }
                }
            }
        }

        return $tokens;
    }
}
