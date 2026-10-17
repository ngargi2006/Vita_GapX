<?php

declare(strict_types=1);

namespace VitaGap\Tests\Unit;

use InvalidArgumentException;
use VitaGap\Http\Validator;
use VitaGap\Tests\TestCase;

final class ValidatorTest extends TestCase
{
    public function testValidInputPassesValidation(): void
    {
        $input = [
            'organism' => '<b>Bacillus subtilis</b>',
            'naming' => 'bsubtilis',
            'genome_type' => 'complete',
            'completeness' => 99.8,
            'scope' => 'closed',
            'format' => 'plain_list',
            'data' => 'RibA -> RibB -> RibD',
            'vitamins' => ['B2', 'B1'],
            'resolve' => ['ribc' => 'K11753'],
            'store_input' => true,
        ];

        $validated = Validator::validateAnalysisInput($input);

        $this->assertSame('Bacillus subtilis', $validated['organism']);
        $this->assertSame('bsubtilis', $validated['naming']);
        $this->assertSame('complete', $validated['genome_type']);
        $this->assertSame(99.8, $validated['completeness']);
        $this->assertSame('closed', $validated['scope']);
        $this->assertSame('plain_list', $validated['format']);
        $this->assertSame(['B2', 'B1'], $validated['vitamins']);
        $this->assertSame(['ribc' => 'K11753'], $validated['resolve']);
        $this->assertTrue($validated['store_input']);
    }

    public function testMissingDataThrowsException(): void
    {
        $this->assertTrue(true);
        try {
            Validator::validateAnalysisInput(['organism' => 'Test', 'data' => '']);
            $this->assertTrue(false, 'Should have thrown InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('data', $e->getMessage());
        }
    }

    public function testInvalidCompletenessRangeThrowsException(): void
    {
        try {
            Validator::validateAnalysisInput([
                'data' => 'RibA',
                'completeness' => 120.0,
            ]);
            $this->assertTrue(false, 'Should have thrown InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Completeness', $e->getMessage());
        }
    }
}
