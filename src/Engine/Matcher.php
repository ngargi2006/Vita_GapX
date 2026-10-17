<?php

declare(strict_types=1);

namespace VitaGap\Engine;

use VitaGap\Input\Token;

final class Matcher
{
    /**
     * Paralog pairs where symbol-only matching carries high ambiguity risk.
     *
     * @var array<string, string>
     */
    private const PARALOG_WARNINGS = [
        'thif' => 'moeB',
        'moeb' => 'thiF',
        'this' => 'moaD',
        'moad' => 'thiS',
        'pane' => 'ilvC',
        'ilvc' => 'panE',
    ];

    /**
     * @param array<string, mixed> $kb
     */
    public function __construct(
        private readonly array $kb
    ) {
    }

    /**
     * Match a collection of tokens against the KB.
     *
     * @param array<Token> $tokens
     * @param string $namingConvention 'generic' | 'ecoli' | 'bsubtilis' | 'other'
     * @param array<string, string> $resolutions Map of raw symbol => selected KO
     * @return array{
     *   matched_kos: array<string, array{ko: string, q: float, tokens: array<Token>, warnings: array<string>}>,
     *   ambiguities: array<string, array{symbol: string, options: array<array{ko: string, naming: string, definition: string}>}>,
     *   unknowns: array<string, array{token: string, suggestions: array<string>}>
     * }
     */
    public function match(
        array $tokens,
        string $namingConvention = 'generic',
        array $resolutions = []
    ): array {
        $naming = strtolower($namingConvention);
        $aliasIndex = $this->buildAliasIndex();
        $ecIndex = $this->buildEcIndex();
        $knownSymbols = array_keys($aliasIndex);

        $matchedKos = [];
        $ambiguities = [];
        $unknowns = [];

        foreach ($tokens as $token) {
            if ($token->type === Token::TYPE_KO) {
                $ko = strtoupper($token->value);
                $this->addKoMatch($matchedKos, $ko, $token->quality, $token);
                continue;
            }

            if ($token->type === Token::TYPE_EC) {
                $ec = $token->value;
                if (isset($ecIndex[$ec])) {
                    $kosForEc = $ecIndex[$ec];
                    $isShared = count($kosForEc) > 1;
                    $q = $isShared ? 0.5 : 0.8;
                    foreach ($kosForEc as $ko) {
                        $this->addKoMatch($matchedKos, $ko, $q, $token, $isShared ? ["EC {$ec} is shared across multiple steps"] : []);
                    }
                } else {
                    $unknowns[$token->raw] = [
                        'token' => $token->raw,
                        'suggestions' => [],
                    ];
                }
                continue;
            }

            if ($token->type === Token::TYPE_SYMBOL) {
                $sym = strtolower($token->value);

                // Check manual user resolution first
                if (isset($resolutions[$sym])) {
                    $resolvedKo = strtoupper($resolutions[$sym]);
                    $this->addKoMatch($matchedKos, $resolvedKo, 0.8, $token, ["Manually resolved by user"]);
                    continue;
                }

                if (!isset($aliasIndex[$sym])) {
                    // Check for near-matches (edit distance <= 1)
                    $suggestions = [];
                    foreach ($knownSymbols as $known) {
                        if (levenshtein($sym, $known) <= 1) {
                            $suggestions[] = $known;
                            if (count($suggestions) >= 3) {
                                break;
                            }
                        }
                    }

                    $unknowns[$token->raw] = [
                        'token' => $token->raw,
                        'suggestions' => $suggestions,
                    ];
                    continue;
                }

                $entries = $aliasIndex[$sym];
                $resolved = $this->resolveSymbol($sym, $entries, $naming);

                if ($resolved['status'] === 'ambiguous') {
                    $ambiguities[$sym] = [
                        'symbol' => $sym,
                        'options' => $resolved['options'],
                    ];
                } elseif ($resolved['status'] === 'matched') {
                    $ko = $resolved['ko'];
                    $q = $resolved['q'];
                    $warnings = $resolved['warnings'];

                    if (isset(self::PARALOG_WARNINGS[$sym])) {
                        $q = min($q, 0.6);
                        $warnings[] = sprintf("Potential paralog collision with %s; KO verification recommended.", self::PARALOG_WARNINGS[$sym]);
                    }

                    $this->addKoMatch($matchedKos, $ko, $q, $token, $warnings);
                }
            }
        }

        return [
            'matched_kos' => $matchedKos,
            'ambiguities' => array_values($ambiguities),
            'unknowns' => array_values($unknowns),
        ];
    }

    /**
     * Resolve a gene symbol across naming conventions.
     *
     * @param array<array{alias: string, ko: string, naming: string}> $entries
     * @return array{status: string, ko?: string, q?: float, warnings?: array<string>, options?: array<mixed>}
     */
    private function resolveSymbol(string $sym, array $entries, string $naming): array
    {
        // 1. Exact match for chosen convention
        $conventionMatches = array_filter($entries, static fn($e) => strtolower($e['naming']) === $naming);
        if (count($conventionMatches) === 1) {
            $entry = reset($conventionMatches);
            $hasOtherConventions = count(array_unique(array_column($entries, 'ko'))) > 1;
            $warnings = [];
            $q = !empty($entry['verify']) ? 0.6 : 0.8;
            if ($hasOtherConventions) {
                $warnings[] = sprintf("Symbol '%s' has different functions in other naming conventions.", $sym);
            }
            return [
                'status' => 'matched',
                'ko' => $entry['ko'],
                'q' => $q,
                'warnings' => $warnings,
            ];
        }

        // 2. Generic entry fallback
        $genericMatches = array_filter($entries, static fn($e) => strtolower($e['naming']) === 'generic');
        if (count($genericMatches) === 1) {
            $entry = reset($genericMatches);
            $hasOtherConventions = count(array_unique(array_column($entries, 'ko'))) > 1;
            $warnings = [];
            // If user asked for a specific convention (e.g. ecoli) and we had to fall back to generic, discount to 0.6
            $q = ($naming !== 'generic' && $hasOtherConventions) ? 0.6 : (!empty($entry['verify']) ? 0.6 : 0.8);
            if ($hasOtherConventions) {
                $warnings[] = sprintf("Symbol '%s' differs in specific organism naming traditions.", $sym);
            }
            return [
                'status' => 'matched',
                'ko' => $entry['ko'],
                'q' => $q,
                'warnings' => $warnings,
            ];
        }

        // 3. Check if all entries point to the same KO anyway
        $uniqueKos = array_unique(array_column($entries, 'ko'));
        if (count($uniqueKos) === 1) {
            return [
                'status' => 'matched',
                'ko' => reset($uniqueKos),
                'q' => 0.8,
                'warnings' => [],
            ];
        }

        // 4. Truly ambiguous
        $options = [];
        foreach ($entries as $e) {
            $options[] = [
                'ko' => $e['ko'],
                'naming' => $e['naming'],
                'definition' => $this->getKoDefinition($e['ko']),
            ];
        }

        return [
            'status' => 'ambiguous',
            'options' => $options,
        ];
    }

    /**
     * @param array<string, array{ko: string, q: float, tokens: array<Token>, warnings: array<string>}> $matchedKos
     * @param array<string> $warnings
     */
    private function addKoMatch(
        array &$matchedKos,
        string $ko,
        float $q,
        Token $token,
        array $warnings = []
    ): void {
        if (!isset($matchedKos[$ko])) {
            $matchedKos[$ko] = [
                'ko' => $ko,
                'q' => $q,
                'tokens' => [$token],
                'warnings' => $warnings,
            ];
        } else {
            // Keep highest quality match
            if ($q > $matchedKos[$ko]['q']) {
                $matchedKos[$ko]['q'] = $q;
            }
            $matchedKos[$ko]['tokens'][] = $token;
            if (count($warnings) > 0) {
                $matchedKos[$ko]['warnings'] = array_unique(array_merge($matchedKos[$ko]['warnings'], $warnings));
            }
        }
    }

    /**
     * Build an index of aliases from KB.
     *
     * @return array<string, array<array{alias: string, ko: string, naming: string}>>
     */
    private function buildAliasIndex(): array
    {
        $index = [];
        foreach ($this->kb['vitamins'] ?? [] as $v) {
            foreach ($v['aliases'] ?? [] as $aliasEntry) {
                $alias = strtolower(trim($aliasEntry['alias']));
                if (!isset($index[$alias])) {
                    $index[$alias] = [];
                }
                $index[$alias][] = $aliasEntry;
            }
        }
        return $index;
    }

    /**
     * Build an index of EC numbers from pathway steps.
     *
     * @return array<string, array<string>> Map of EC => [KO, ...]
     */
    private function buildEcIndex(): array
    {
        $index = [];
        foreach ($this->kb['vitamins'] ?? [] as $v) {
            foreach ($v['pathways'] ?? [] as $pw) {
                foreach ($pw['steps'] ?? [] as $step) {
                    if (empty($step['ec'])) {
                        continue;
                    }
                    $ecs = preg_split('/[\s,]+/', $step['ec']);
                    if ($ecs === false) {
                        continue;
                    }
                    foreach ($ecs as $ec) {
                        $ec = trim($ec);
                        if ($ec === '') {
                            continue;
                        }
                        if (!isset($index[$ec])) {
                            $index[$ec] = [];
                        }
                        foreach ($step['options'] ?? [] as $opt) {
                            $kos = explode('+', $opt);
                            foreach ($kos as $k) {
                                $k = trim($k);
                                if ($k !== '' && !in_array($k, $index[$ec], true)) {
                                    $index[$ec][] = $k;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $index;
    }

    private function getKoDefinition(string $ko): string
    {
        // Search through steps to find step name associated with this KO
        foreach ($this->kb['vitamins'] ?? [] as $v) {
            foreach ($v['pathways'] ?? [] as $pw) {
                foreach ($pw['steps'] ?? [] as $step) {
                    foreach ($step['options'] ?? [] as $opt) {
                        if (str_contains($opt, $ko)) {
                            return $step['name'];
                        }
                    }
                }
            }
        }
        return 'Enzyme associated with ' . $ko;
    }
}
