<?php

declare(strict_types=1);

namespace VitaGap\Input;

final class Token
{
    public const TYPE_KO = 'KO';
    public const TYPE_EC = 'EC';
    public const TYPE_SYMBOL = 'SYMBOL';
    public const TYPE_UNKNOWN = 'UNKNOWN';

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $raw,
        public readonly string $type,
        public readonly string $value,
        public float $quality = 0.8,
        public readonly ?string $geneId = null,
        public readonly array $metadata = []
    ) {
    }

    /**
     * Convert token to associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'raw' => $this->raw,
            'type' => $this->type,
            'value' => $this->value,
            'quality' => $this->quality,
            'gene_id' => $this->geneId,
            'metadata' => $this->metadata,
        ];
    }
}
