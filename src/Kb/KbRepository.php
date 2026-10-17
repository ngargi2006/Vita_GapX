<?php

declare(strict_types=1);

namespace VitaGap\Kb;

final class KbRepository
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, array<string, mixed>> */
    private array $vitaminsByCode = [];

    /** @var array<string, array<string, mixed>> */
    private array $pathwaysByCode = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $aliasesBySymbol = [];

    public function __construct(?array $data = null)
    {
        $this->data = $data ?? KbCache::load();
        $this->index();
    }

    private function index(): void
    {
        $this->vitaminsByCode = [];
        $this->pathwaysByCode = [];
        $this->aliasesBySymbol = [];

        foreach ($this->data['vitamins'] ?? [] as $vit) {
            $vCode = strtoupper($vit['code']);
            $this->vitaminsByCode[$vCode] = $vit;

            foreach ($vit['pathways'] ?? [] as $pw) {
                $pCode = strtoupper($pw['code']);
                $pw['vitamin_code'] = $vCode;
                $this->pathwaysByCode[$pCode] = $pw;
            }

            foreach ($vit['aliases'] ?? [] as $alias) {
                $sym = strtolower(trim($alias['alias']));
                $alias['vitamin_code'] = $vCode;
                $this->aliasesBySymbol[$sym][] = $alias;
            }
        }
    }

    public function getVersion(): string
    {
        return (string) ($this->data['kb_version'] ?? '1.0.0');
    }

    public function getKeggRelease(): string
    {
        return (string) ($this->data['kegg_release'] ?? 'Unknown');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getVitamins(): array
    {
        return array_values($this->vitaminsByCode);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getVitamin(string $code): ?array
    {
        return $this->vitaminsByCode[strtoupper($code)] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPathway(string $code): ?array
    {
        return $this->pathwaysByCode[strtoupper($code)] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAliasesBySymbol(string $symbol): array
    {
        return $this->aliasesBySymbol[strtolower(trim($symbol))] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getAllKos(): array
    {
        $kos = [];

        foreach ($this->pathwaysByCode as $pw) {
            foreach ($pw['steps'] ?? [] as $step) {
                foreach ($step['options'] ?? [] as $opt) {
                    $parts = explode('+', $opt);
                    foreach ($parts as $p) {
                        $p = trim($p);
                        if (preg_match('/^K\d{5}$/', $p)) {
                            $kos[$p] = true;
                        }
                    }
                }
            }
        }

        foreach ($this->aliasesBySymbol as $list) {
            foreach ($list as $entry) {
                $k = trim($entry['ko'] ?? '');
                if (preg_match('/^K\d{5}$/', $k)) {
                    $kos[$k] = true;
                }
            }
        }

        $result = array_keys($kos);
        sort($result);
        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRules(string $vitaminCode): array
    {
        $vit = $this->getVitamin($vitaminCode);
        return $vit['rules'] ?? [];
    }
}
