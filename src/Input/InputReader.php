<?php

declare(strict_types=1);

namespace VitaGap\Input;

use InvalidArgumentException;
use VitaGap\Input\Parsers\EggNogParser;
use VitaGap\Input\Parsers\GenericCsvParser;
use VitaGap\Input\Parsers\KoalaParser;
use VitaGap\Input\Parsers\KofamScanParser;
use VitaGap\Input\Parsers\KoListParser;
use VitaGap\Input\Parsers\PlainListParser;
use VitaGap\Input\Parsers\ProkkaParser;

final class InputReader
{
    public const MAX_BYTES = 5242880; // 5 MB
    public const MAX_LINES = 50000;

    public function __construct(
        private readonly FormatDetector $detector = new FormatDetector()
    ) {
    }

    /**
     * Read and parse input data.
     *
     * @return array{format: string, tokens: array<Token>}
     */
    public function read(string $content, string $format = FormatDetector::FORMAT_AUTO): array
    {
        $byteLength = strlen($content);
        if ($byteLength > self::MAX_BYTES) {
            throw new InvalidArgumentException(
                sprintf("Input size (%d bytes) exceeds the 5 MB limit.", $byteLength)
            );
        }

        $lines = preg_split('/[\r\n]+/', $content);
        $lineCount = $lines !== false ? count($lines) : 0;
        if ($lineCount > self::MAX_LINES) {
            throw new InvalidArgumentException(
                sprintf("Input line count (%d lines) exceeds the maximum of %d lines.", $lineCount, self::MAX_LINES)
            );
        }

        $detectedFormat = ($format === FormatDetector::FORMAT_AUTO || $format === '')
            ? $this->detector->detect($content)
            : $format;

        $parser = match ($detectedFormat) {
            FormatDetector::FORMAT_KO_LIST => new KoListParser(),
            FormatDetector::FORMAT_KOFAMSCAN => new KofamScanParser(),
            FormatDetector::FORMAT_KOALA => new KoalaParser(),
            FormatDetector::FORMAT_EGGNOG => new EggNogParser(),
            FormatDetector::FORMAT_PROKKA => new ProkkaParser(),
            FormatDetector::FORMAT_CSV => new GenericCsvParser(),
            default => new PlainListParser(),
        };

        $tokens = $parser->parse($content);

        return [
            'format' => $detectedFormat,
            'tokens' => $tokens,
        ];
    }
}
