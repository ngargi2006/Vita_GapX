<?php

declare(strict_types=1);

namespace VitaGap\Http;

use InvalidArgumentException;

final class Validator
{
    public const VALID_NAMING = ['generic', 'ecoli', 'bsubtilis', 'other'];
    public const VALID_GENOME_TYPES = ['complete', 'draft', 'mag', 'unknown'];
    public const VALID_SCOPES = ['open', 'closed'];
    public const VALID_FORMATS = ['auto', 'plain_list', 'ko_list', 'kofamscan', 'koala', 'eggnog', 'prokka', 'csv'];
    public const VALID_VITAMINS = ['B1', 'B2', 'B3', 'B5', 'B6', 'B7', 'B9', 'B12', 'K2'];

    /**
     * Validate and sanitize input payload for analysis.
     *
     * @param array<string, mixed> $input
     * @return array{
     *   organism: string,
     *   naming: string,
     *   genome_type: string,
     *   completeness: ?float,
     *   scope: string,
     *   format: string,
     *   data: string,
     *   vitamins: array<string>,
     *   resolve: array<string, string>,
     *   store_input: bool
     * }
     */
    public static function validateAnalysisInput(array $input): array
    {
        $data = (string) ($input['data'] ?? '');
        if (trim($data) === '') {
            throw new InvalidArgumentException("Missing required parameter 'data'.");
        }

        $organism = trim((string) ($input['organism'] ?? ''));
        if (strlen($organism) > 160) {
            $organism = substr($organism, 0, 160);
        }
        $organism = strip_tags($organism);

        $naming = strtolower(trim((string) ($input['naming'] ?? 'generic')));
        if (!in_array($naming, self::VALID_NAMING, true)) {
            $naming = 'generic';
        }

        $genomeType = strtolower(trim((string) ($input['genome_type'] ?? 'unknown')));
        if (!in_array($genomeType, self::VALID_GENOME_TYPES, true)) {
            $genomeType = 'unknown';
        }

        $completeness = null;
        if (isset($input['completeness']) && $input['completeness'] !== '' && $input['completeness'] !== null) {
            $c = (float) $input['completeness'];
            if ($c < 0.0 || $c > 100.0) {
                throw new InvalidArgumentException("Completeness percentage must be between 0 and 100.");
            }
            $completeness = round($c, 2);
        }

        $scope = strtolower(trim((string) ($input['scope'] ?? 'open')));
        if (!in_array($scope, self::VALID_SCOPES, true)) {
            $scope = 'open';
        }

        $format = strtolower(trim((string) ($input['format'] ?? 'auto')));
        if (!in_array($format, self::VALID_FORMATS, true)) {
            $format = 'auto';
        }

        $vitamins = [];
        if (isset($input['vitamins']) && is_array($input['vitamins'])) {
            foreach ($input['vitamins'] as $v) {
                $code = strtoupper(trim((string) $v));
                if (in_array($code, self::VALID_VITAMINS, true)) {
                    $vitamins[] = $code;
                }
            }
        }
        if (count($vitamins) === 0) {
            $vitamins = self::VALID_VITAMINS;
        }

        $resolve = [];
        if (isset($input['resolve']) && is_array($input['resolve'])) {
            foreach ($input['resolve'] as $sym => $ko) {
                $cleanSym = strtolower(trim((string) $sym));
                $cleanKo = strtoupper(trim((string) $ko));
                if (preg_match('/^K\d{5}$/', $cleanKo)) {
                    $resolve[$cleanSym] = $cleanKo;
                }
            }
        }

        $storeInput = !empty($input['store_input']);

        return [
            'organism' => $organism,
            'naming' => $naming,
            'genome_type' => $genomeType,
            'completeness' => $completeness,
            'scope' => $scope,
            'format' => $format,
            'data' => $data,
            'vitamins' => array_values(array_unique($vitamins)),
            'resolve' => $resolve,
            'store_input' => $storeInput,
        ];
    }
}
