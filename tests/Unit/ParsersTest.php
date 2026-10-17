<?php

declare(strict_types=1);

namespace VitaGap\Tests\Unit;

use VitaGap\Input\Parsers\EggNogParser;
use VitaGap\Input\Parsers\GenericCsvParser;
use VitaGap\Input\Parsers\KoalaParser;
use VitaGap\Input\Parsers\KofamScanParser;
use VitaGap\Input\Parsers\KoListParser;
use VitaGap\Input\Parsers\PlainListParser;
use VitaGap\Input\Parsers\ProkkaParser;
use VitaGap\Tests\TestCase;

final class ParsersTest extends TestCase
{
    public function testPlainListParser(): void
    {
        $parser = new PlainListParser();
        $tokens = $parser->parse("RibA -> RibB -> RibD");
        $this->assertCount(3, $tokens);
        $this->assertSame('riba', $tokens[0]->value);
    }

    public function testKoListParser(): void
    {
        $parser = new KoListParser();
        $tokens = $parser->parse("K01497\nko:K02858\n# comment\nK11752");
        $this->assertCount(3, $tokens);
        $this->assertSame('K01497', $tokens[0]->value);
        $this->assertSame('K02858', $tokens[1]->value);
        $this->assertSame('K11752', $tokens[2]->value);
    }

    public function testKofamScanDetailParser(): void
    {
        $parser = new KofamScanParser();
        $content = <<<EOF
# gene name        KO     thrshld  score   E-value  KO definition
* b0414            K01497  340.2    450.1   1.2e-130 GTP cyclohydrolase II
  b1234            K99999  200.0    100.0   0.05     insignificant hit
* b3041            K00793  250.0    310.4   4.5e-85  riboflavin synthase
EOF;
        $tokens = $parser->parse($content);
        $this->assertCount(2, $tokens);
        $this->assertSame('K01497', $tokens[0]->value);
        $this->assertSame('K00793', $tokens[1]->value);
        $this->assertSame(1.0, $tokens[0]->quality);
    }

    public function testKoalaParser(): void
    {
        $parser = new KoalaParser();
        $content = "geneA\tK01497\ngeneB\t\ngeneC\tK02858\n";
        $tokens = $parser->parse($content);
        $this->assertCount(2, $tokens);
        $this->assertSame('K01497', $tokens[0]->value);
        $this->assertSame('K02858', $tokens[1]->value);
        $this->assertSame(0.9, $tokens[0]->quality);
    }

    public function testEggNogParser(): void
    {
        $parser = new EggNogParser();
        $content = <<<EOF
#query\tseed_ortholog\tevalue\tscore\teggNOG_OGs\tmax_annot_lvl\tCOG_category\tDescription\tPreferred_name\tGOs\tEC\tKEGG_ko\tKEGG_Pathway
gene1\t...\t...\t...\t...\t...\t...\t...\tribA\t...\t3.5.4.25\tko:K01497\t...
EOF;
        $tokens = $parser->parse($content);
        $this->assertCount(3, $tokens); // KO, SYMBOL, EC
        $kos = array_filter($tokens, static fn($t) => $t->type === 'KO');
        $this->assertCount(1, $kos);
        $this->assertSame('K01497', reset($kos)->value);
    }

    public function testProkkaParser(): void
    {
        $parser = new ProkkaParser();
        $content = <<<EOF
locus_tag\tftype\tlength_bp\tgene\tEC_number\tCOG\tproduct
LOCUS_1\tCDS\t500\tribB\t4.1.99.12\tCOG1\tDHBP synthase
EOF;
        $tokens = $parser->parse($content);
        $this->assertCount(2, $tokens);
        $this->assertSame('ribb', $tokens[0]->value);
        $this->assertSame('4.1.99.12', $tokens[1]->value);
    }

    public function testGenericCsvParser(): void
    {
        $parser = new GenericCsvParser();
        $content = "gene,ko,ec\nribD,K11752,3.5.4.26\n";
        $tokens = $parser->parse($content);
        $this->assertCount(3, $tokens);
    }
}
