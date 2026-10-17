<?php

declare(strict_types=1);

namespace VitaGap\Input;

final class Tokenizer
{
    /**
     * Regex for arrows and delimiters:
     * - Arrows: ⟶, →, ->, =>
     * - Delimiters: newlines, semicolons, commas, tabs, spaces
     */
    private const ARROW_DELIMITERS = '/(?:\x{27F6}|\x{2192}|->|=>|[\r\n,;\t]+)/u';
    private const KO_PATTERN = '/^K\d{5}$/i';
    private const EC_PATTERN = '/^(?:EC:?\s*)?(\d+\.\d+\.\d+\.(?:\d+|-))$/i';
    private const SYMBOL_PATTERN = '/^[a-zA-Z0-9_\-\.\'\/\(\)]+$/';

    /**
     * Tokenize raw input string into an array of Token instances.
     *
     * @return array<Token>
     */
    public function tokenize(string $input, float $defaultQuality = 0.8): array
    {
        if (trim($input) === '') {
            return [];
        }

        // Split on arrows and punctuation delimiters first
        $rawSegments = preg_split(self::ARROW_DELIMITERS, $input);
        if ($rawSegments === false) {
            $rawSegments = [$input];
        }

        $tokens = [];
        $seen = [];

        foreach ($rawSegments as $segment) {
            // Further split whitespace if any remain in segment
            $parts = preg_split('/\s+/', trim($segment));
            if ($parts === false) {
                continue;
            }

            foreach ($parts as $part) {
                $trimmed = trim($part, " \t\n\r\0\x0B\"'()[],;");
                if ($trimmed === '') {
                    continue;
                }

                $token = $this->classify($trimmed, $defaultQuality);
                $key = $token->type . ':' . $token->value;
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $tokens[] = $token;
                }
            }
        }

        return $tokens;
    }

    /**
     * Classify an individual raw token string into a typed Token.
     */
    public function classify(string $raw, float $defaultQuality = 0.8, ?string $geneId = null): Token
    {
        $clean = trim($raw);

        // Check if KO term: e.g. K01497 or ko:K01497
        if (preg_match('/^(?:ko:)?(K\d{5})$/i', $clean, $matches)) {
            return new Token(
                raw: $clean,
                type: Token::TYPE_KO,
                value: strtoupper($matches[1]),
                quality: 0.9,
                geneId: $geneId
            );
        }

        // Check if EC number
        if (preg_match(self::EC_PATTERN, $clean, $matches)) {
            return new Token(
                raw: $clean,
                type: Token::TYPE_EC,
                value: $matches[1],
                quality: $defaultQuality,
                geneId: $geneId
            );
        }

        // Check if gene symbol
        if (preg_match(self::SYMBOL_PATTERN, $clean)) {
            return new Token(
                raw: $clean,
                type: Token::TYPE_SYMBOL,
                value: strtolower($clean),
                quality: $defaultQuality,
                geneId: $geneId
            );
        }

        return new Token(
            raw: $clean,
            type: Token::TYPE_UNKNOWN,
            value: $clean,
            quality: 0.0,
            geneId: $geneId
        );
    }
}
