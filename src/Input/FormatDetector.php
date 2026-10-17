<?php

declare(strict_types=1);

namespace VitaGap\Input;

final class FormatDetector
{
    public const FORMAT_AUTO = 'auto';
    public const FORMAT_PLAIN_LIST = 'plain_list';
    public const FORMAT_KO_LIST = 'ko_list';
    public const FORMAT_KOFAMSCAN = 'kofamscan';
    public const FORMAT_KOALA = 'koala';
    public const FORMAT_EGGNOG = 'eggnog';
    public const FORMAT_PROKKA = 'prokka';
    public const FORMAT_CSV = 'csv';

    /**
     * Detect the format of an input string.
     */
    public function detect(string $content): string
    {
        $sample = substr(trim($content), 0, 8192);
        $lines = preg_split('/[\r\n]+/', $sample);
        if ($lines === false || count($lines) === 0) {
            return self::FORMAT_PLAIN_LIST;
        }

        // Check for eggNOG headers or comment lines
        foreach ($lines as $line) {
            if (str_starts_with($line, '#') && (str_contains($line, 'Preferred_name') || str_contains($line, 'KEGG_ko') || str_contains($line, 'seed_ortholog'))) {
                return self::FORMAT_EGGNOG;
            }
        }

        // Check for Prokka headers
        foreach ($lines as $line) {
            if (str_contains($line, 'locus_tag') && (str_contains($line, 'gene') || str_contains($line, 'EC_number'))) {
                return self::FORMAT_PROKKA;
            }
        }

        // Check for KofamScan detail format (# ... threshold score) or lines starting with '*'
        foreach ($lines as $line) {
            if (str_starts_with($line, '#') && str_contains($line, 'threshold') && str_contains($line, 'score')) {
                return self::FORMAT_KOFAMSCAN;
            }
            if (str_starts_with($line, '* ') && preg_match('/\s+K\d{5}\s+/', $line)) {
                return self::FORMAT_KOFAMSCAN;
            }
        }

        // Check for CSV / TSV with recognized headers
        $firstLine = $lines[0];
        $headers = str_getcsv($firstLine, str_contains($firstLine, "\t") ? "\t" : ',', '"', "\\");
        $headerWords = array_map(static fn($h) => strtolower(trim((string) $h)), $headers);
        if (
            (in_array('ko', $headerWords, true) || in_array('kegg_ko', $headerWords, true)) &&
            (in_array('gene', $headerWords, true) || in_array('symbol', $headerWords, true) || in_array('locus_tag', $headerWords, true))
        ) {
            return self::FORMAT_CSV;
        }

        // Check if pure KO list or two-column KOALA
        $nonEmptyLines = array_values(array_filter($lines, static fn($l) => trim($l) !== '' && !str_starts_with($l, '#')));
        if (count($nonEmptyLines) > 0) {
            $koOnlyCount = 0;
            $twoColKoCount = 0;

            foreach ($nonEmptyLines as $line) {
                $trimmed = trim($line);
                if (preg_match('/^(?:ko:)?K\d{5}$/i', $trimmed)) {
                    $koOnlyCount++;
                } elseif (preg_match('/^[^\t\s]+\s+(?:ko:)?K\d{5}$/i', $trimmed)) {
                    $twoColKoCount++;
                }
            }

            if ($koOnlyCount / count($nonEmptyLines) >= 0.7) {
                return self::FORMAT_KO_LIST;
            }

            if ($twoColKoCount / count($nonEmptyLines) >= 0.7) {
                return self::FORMAT_KOALA;
            }
        }

        return self::FORMAT_PLAIN_LIST;
    }
}
