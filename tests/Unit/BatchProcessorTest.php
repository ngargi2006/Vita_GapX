<?php

declare(strict_types=1);

namespace VitaGap\Tests\Unit;

use VitaGap\Engine\BatchProcessor;
use VitaGap\Tests\TestCase;

final class BatchProcessorTest extends TestCase
{
    private BatchProcessor $processor;

    public function setUp(): void
    {
        $this->processor = new BatchProcessor();
    }

    public function testBatchProcessingAndCrossFeedingEdges(): void
    {
        $batchInput = [
            'name' => 'Synthetic Trio',
            'genomes' => [
                [
                    'organism' => 'Producer Organism',
                    'naming' => 'generic',
                    'genome_type' => 'complete',
                    'completeness' => 100.0,
                    'scope' => 'closed',
                    'data' => 'RibA -> RibB -> RibD -> RibH -> RibE',
                ],
                [
                    'organism' => 'Consumer with Transporter',
                    'naming' => 'generic',
                    'genome_type' => 'complete',
                    'completeness' => 100.0,
                    'scope' => 'closed',
                    'data' => 'RibU', // Riboflavin ECF transporter
                ],
                [
                    'organism' => 'Unrelated Auxotroph',
                    'naming' => 'generic',
                    'genome_type' => 'complete',
                    'completeness' => 100.0,
                    'scope' => 'closed',
                    'data' => 'Unrelated_Gene_X',
                ],
            ],
        ];

        $start = microtime(true);
        $result = $this->processor->process($batchInput);
        $duration = microtime(true) - $start;

        // Check performance (< 2s for 3 genomes)
        $this->assertTrue($duration < 2.0, "Batch took {$duration}s, should be < 2s");

        $this->assertSame('Synthetic Trio', $result['name']);
        $this->assertSame(3, $result['genome_count']);

        // Check matrix
        $this->assertCount(3, $result['matrix']);
        $producerRow = $result['matrix'][0];
        $consumerRow = $result['matrix'][1];

        $this->assertSame('PROTOTROPH', $producerRow['vitamins']['B2']['call']);
        $this->assertSame('AUXOTROPH', $consumerRow['vitamins']['B2']['call']);
        $this->assertTrue($consumerRow['vitamins']['B2']['transporter_found']);

        // Check cross-feeding network
        $network = $result['network'];
        $this->assertCount(3, $network['nodes']);

        // Check edges for B2
        $b2Edges = array_filter($network['edges'], static fn($e) => $e['data']['vitamin'] === 'B2');
        $this->assertTrue(count($b2Edges) >= 1);

        // Find edge from Producer (g_1) to Consumer with Transporter (g_2)
        $edgeToConsumer = null;
        foreach ($b2Edges as $edge) {
            if ($edge['data']['source'] === 'g_1' && $edge['data']['target'] === 'g_2') {
                $edgeToConsumer = $edge;
                break;
            }
        }

        $this->assertNotNull($edgeToConsumer, 'Edge from producer to consumer should exist');
        $this->assertSame(2.5, $edgeToConsumer['data']['weight']);
        $this->assertTrue($edgeToConsumer['data']['has_transporter']);

        // Check community coverage
        $coverage = $result['community_coverage'];
        $this->assertTrue($coverage['vitamins']['B2']['is_covered']);
        $this->assertSame(1, $coverage['vitamins']['B2']['producer_count']);

        // Check GraphML generation
        $graphml = $this->processor->toGraphML($result);
        $this->assertStringContainsString('<?xml version="1.0"', $graphml);
        $this->assertStringContainsString('<graphml', $graphml);
        $this->assertStringContainsString('Producer Organism', $graphml);
        $this->assertStringContainsString('Consumer with Transporter', $graphml);
        $this->assertStringContainsString('<data key="d_vit">B2</data>', $graphml);
    }
}
