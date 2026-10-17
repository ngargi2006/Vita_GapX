<?php

declare(strict_types=1);

namespace VitaGap\Engine;

use InvalidArgumentException;
use VitaGap\Http\Validator;

final class BatchProcessor
{
    public const MAX_GENOMES = 50;

    public function __construct(
        private readonly Analyzer $analyzer = new Analyzer()
    ) {
    }

    /**
     * Process a batch of bacterial genomes.
     *
     * @param array{
     *   name?: string,
     *   genomes: array<array<string, mixed>>
     * } $batchInput
     * @return array<string, mixed>
     */
    public function process(array $batchInput): array
    {
        $genomes = $batchInput['genomes'] ?? [];
        if (!is_array($genomes) || count($genomes) === 0) {
            throw new InvalidArgumentException("Batch must contain at least 1 genome in 'genomes'.");
        }

        if (count($genomes) > self::MAX_GENOMES) {
            throw new InvalidArgumentException(
                sprintf("Batch exceeds the maximum limit of %d genomes (provided %d).", self::MAX_GENOMES, count($genomes))
            );
        }

        $communityName = trim((string) ($batchInput['name'] ?? 'Microbial Community'));
        if ($communityName === '') {
            $communityName = 'Microbial Community';
        }

        $analyzedGenomes = [];
        $matrix = [];
        $vitaminCodes = Validator::VALID_VITAMINS;

        // Run analysis for each genome in batch
        foreach ($genomes as $idx => $genomeInput) {
            $validated = Validator::validateAnalysisInput($genomeInput);
            $genomeName = $validated['organism'] ?: sprintf("Genome_%02d", $idx + 1);
            $validated['organism'] = $genomeName;

            $report = $this->analyzer->analyze($validated);
            $genomeId = sprintf("g_%d", $idx + 1);

            $genomeRow = [
                'id' => $genomeId,
                'name' => $genomeName,
                'genome_type' => $validated['genome_type'],
                'scope' => $validated['scope'],
                'completeness' => $validated['completeness'],
                'vitamins' => [],
            ];

            foreach ($vitaminCodes as $vCode) {
                $vData = $report['vitamins'][$vCode] ?? null;
                if ($vData !== null) {
                    // Check if transporter is present
                    $transporterFound = false;
                    foreach ($vData['transport'] ?? [] as $tpw) {
                        foreach ($tpw['steps'] ?? [] as $tstep) {
                            if (($tstep['status'] ?? '') === 'present') {
                                $transporterFound = true;
                                break 2;
                            }
                        }
                    }

                    $genomeRow['vitamins'][$vCode] = [
                        'call' => $vData['q4_call']['code'],
                        'call_label' => $vData['q4_call']['label'],
                        'weighted_pct' => $vData['q3_completeness']['weighted_pct'],
                        'evidence_score' => $vData['q5_evidence']['score'],
                        'evidence_tier' => $vData['q5_evidence']['tier'],
                        'transporter_found' => $transporterFound,
                    ];
                }
            }

            $analyzedGenomes[$genomeId] = [
                'id' => $genomeId,
                'name' => $genomeName,
                'report' => $report,
            ];

            $matrix[] = $genomeRow;
        }

        // Compute cross-feeding network edges & community coverage
        $network = $this->computeCrossFeedingNetwork($matrix, $vitaminCodes);
        $coverage = $this->computeCommunityCoverage($matrix, $vitaminCodes);

        return [
            'name' => $communityName,
            'genome_count' => count($matrix),
            'matrix' => $matrix,
            'network' => $network,
            'community_coverage' => $coverage,
            'genomes' => $analyzedGenomes,
        ];
    }

    /**
     * Compute directed cross-feeding edges from Producers to Auxotrophs.
     *
     * @param array<array<string, mixed>> $matrix
     * @param array<string> $vitaminCodes
     * @return array{nodes: array<mixed>, edges: array<mixed>}
     */
    private function computeCrossFeedingNetwork(array $matrix, array $vitaminCodes): array
    {
        $nodes = [];
        $edges = [];
        $edgeIdCounter = 1;

        // Create nodes
        foreach ($matrix as $g) {
            $producerCount = 0;
            $auxotrophCount = 0;

            foreach ($g['vitamins'] as $v) {
                if (in_array($v['call'], [RuleEngine::CALL_PROTOTROPH, RuleEngine::CALL_PROBABLE_PROTOTROPH], true)) {
                    $producerCount++;
                } elseif (in_array($v['call'], [RuleEngine::CALL_AUXOTROPH, RuleEngine::CALL_PRECURSOR_DEPENDENT], true)) {
                    $auxotrophCount++;
                }
            }

            $nodes[] = [
                'data' => [
                    'id' => $g['id'],
                    'label' => $g['name'],
                    'producer_count' => $producerCount,
                    'auxotroph_count' => $auxotrophCount,
                    'is_net_producer' => ($producerCount > $auxotrophCount),
                ],
            ];
        }

        // Create cross-feeding edges for each vitamin
        foreach ($vitaminCodes as $vCode) {
            $producers = [];
            $consumers = [];

            foreach ($matrix as $g) {
                $status = $g['vitamins'][$vCode] ?? null;
                if ($status === null) {
                    continue;
                }

                $call = $status['call'];
                if (in_array($call, [RuleEngine::CALL_PROTOTROPH, RuleEngine::CALL_PROBABLE_PROTOTROPH], true)) {
                    $producers[] = $g;
                } elseif (in_array($call, [RuleEngine::CALL_AUXOTROPH, RuleEngine::CALL_PRECURSOR_DEPENDENT], true)) {
                    $consumers[] = $g;
                }
            }

            // Create directed edge from every producer to every consumer of this vitamin
            foreach ($producers as $prod) {
                foreach ($consumers as $cons) {
                    if ($prod['id'] === $cons['id']) {
                        continue;
                    }

                    $consTransporter = $cons['vitamins'][$vCode]['transporter_found'] ?? false;
                    $weight = $consTransporter ? 2.5 : 1.0;

                    $edges[] = [
                        'data' => [
                            'id' => sprintf("e_%d", $edgeIdCounter++),
                            'source' => $prod['id'],
                            'target' => $cons['id'],
                            'source_label' => $prod['name'],
                            'target_label' => $cons['name'],
                            'vitamin' => $vCode,
                            'weight' => $weight,
                            'has_transporter' => $consTransporter,
                        ],
                    ];
                }
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * Compute community autonomy coverage per vitamin.
     *
     * @param array<array<string, mixed>> $matrix
     * @param array<string> $vitaminCodes
     * @return array<string, mixed>
     */
    private function computeCommunityCoverage(array $matrix, array $vitaminCodes): array
    {
        $vitamins = [];
        $coveredCount = 0;

        foreach ($vitaminCodes as $vCode) {
            $producers = [];
            $consumers = [];
            $nonReq = [];

            foreach ($matrix as $g) {
                $call = $g['vitamins'][$vCode]['call'] ?? 'UNKNOWN';
                if (in_array($call, [RuleEngine::CALL_PROTOTROPH, RuleEngine::CALL_PROBABLE_PROTOTROPH], true)) {
                    $producers[] = $g['name'];
                } elseif (in_array($call, [RuleEngine::CALL_AUXOTROPH, RuleEngine::CALL_PRECURSOR_DEPENDENT], true)) {
                    $consumers[] = $g['name'];
                } elseif ($call === RuleEngine::CALL_NOT_REQUIRED) {
                    $nonReq[] = $g['name'];
                }
            }

            $isCovered = count($producers) > 0;
            if ($isCovered) {
                $coveredCount++;
            }

            $vitamins[$vCode] = [
                'vitamin' => $vCode,
                'is_covered' => $isCovered,
                'producers' => $producers,
                'producer_count' => count($producers),
                'consumers' => $consumers,
                'consumer_count' => count($consumers),
                'not_required_count' => count($nonReq),
                'autonomy_status' => $isCovered ? 'Autonomous (Producers Present)' : 'Deficit (No Producers)',
            ];
        }

        return [
            'vitamins' => $vitamins,
            'total_vitamins' => count($vitaminCodes),
            'covered_vitamins' => $coveredCount,
            'community_autonomy_pct' => round(($coveredCount / count($vitaminCodes)) * 100, 1),
        ];
    }

    /**
     * Export network as valid GraphML XML string for Cytoscape.
     *
     * @param array<string, mixed> $batchResult
     */
    public function toGraphML(array $batchResult): string
    {
        $network = $batchResult['network'] ?? ['nodes' => [], 'edges' => []];
        $name = htmlspecialchars((string) ($batchResult['name'] ?? 'Vita-Gap Network'), ENT_XML1);

        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<graphml xmlns="http://graphml.graphdrawing.org/xmlns"';
        $xml[] = '    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';
        $xml[] = '    xsi:schemaLocation="http://graphml.graphdrawing.org/xmlns http://graphml.graphdrawing.org/xmlns/1.0/graphml.xsd">';
        
        // Key definitions
        $xml[] = '  <key id="d_label" for="node" attr.name="label" attr.type="string"/>';
        $xml[] = '  <key id="d_prod" for="node" attr.name="producers" attr.type="int"/>';
        $xml[] = '  <key id="d_aux" for="node" attr.name="auxotrophs" attr.type="int"/>';
        $xml[] = '  <key id="d_vit" for="edge" attr.name="vitamin" attr.type="string"/>';
        $xml[] = '  <key id="d_weight" for="edge" attr.name="weight" attr.type="double"/>';
        $xml[] = '  <key id="d_trans" for="edge" attr.name="has_transporter" attr.type="boolean"/>';

        $xml[] = "  <graph id=\"{$name}\" edgedefault=\"directed\">";

        // Nodes
        foreach ($network['nodes'] as $node) {
            $data = $node['data'];
            $id = htmlspecialchars((string) $data['id'], ENT_XML1);
            $label = htmlspecialchars((string) $data['label'], ENT_XML1);
            $prod = (int) ($data['producer_count'] ?? 0);
            $aux = (int) ($data['auxotroph_count'] ?? 0);

            $xml[] = "    <node id=\"{$id}\">";
            $xml[] = "      <data key=\"d_label\">{$label}</data>";
            $xml[] = "      <data key=\"d_prod\">{$prod}</data>";
            $xml[] = "      <data key=\"d_aux\">{$aux}</data>";
            $xml[] = "    </node>";
        }

        // Edges
        foreach ($network['edges'] as $edge) {
            $data = $edge['data'];
            $id = htmlspecialchars((string) $data['id'], ENT_XML1);
            $source = htmlspecialchars((string) $data['source'], ENT_XML1);
            $target = htmlspecialchars((string) $data['target'], ENT_XML1);
            $vit = htmlspecialchars((string) $data['vitamin'], ENT_XML1);
            $weight = (float) ($data['weight'] ?? 1.0);
            $trans = !empty($data['has_transporter']) ? 'true' : 'false';

            $xml[] = "    <edge id=\"{$id}\" source=\"{$source}\" target=\"{$target}\">";
            $xml[] = "      <data key=\"d_vit\">{$vit}</data>";
            $xml[] = "      <data key=\"d_weight\">{$weight}</data>";
            $xml[] = "      <data key=\"d_trans\">{$trans}</data>";
            $xml[] = "    </edge>";
        }

        $xml[] = '  </graph>';
        $xml[] = '</graphml>';

        return implode("\n", $xml);
    }
}
