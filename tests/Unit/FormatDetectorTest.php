<?php

declare(strict_types=1);

namespace VitaGap\Tests\Unit;

use VitaGap\Input\FormatDetector;
use VitaGap\Tests\TestCase;

final class FormatDetectorTest extends TestCase
{
    private FormatDetector $detector;

    public function setUp(): void
    {
        $this->detector = new FormatDetector();
    }

    public function testDetectPlainList(): void
    {
        $input = "RibA -> RibB -> RibD -> RibH";
        $this->assertSame(FormatDetector::FORMAT_PLAIN_LIST, $this->detector->detect($input));
    }

    public function testDetectKoList(): void
    {
        $input = "K01497\nK02858\nK11752\nK00794\nK00793";
        $this->assertSame(FormatDetector::FORMAT_KO_LIST, $this->detector->detect($input));
    }

    public function testDetectEggNog(): void
    {
        $input = "#query\tseed_ortholog\tevalue\tscore\tPreferred_name\tGOs\tEC\tKEGG_ko\ngene1\t...\t...\t...\tribA\t...\t...\tko:K01497";
        $this->assertSame(FormatDetector::FORMAT_EGGNOG, $this->detector->detect($input));
    }

    public function testDetectProkka(): void
    {
        $input = "locus_tag\tftype\tlength_bp\tgene\tEC_number\tCOG\tproduct\nPROKKA_00001\tCDS\t950\tribA\t3.5.4.25\tCOG0001\tGTP cyclohydrolase";
        $this->assertSame(FormatDetector::FORMAT_PROKKA, $this->detector->detect($input));
    }

    public function testDetectKofamScanDetail(): void
    {
        $input = "# gene name        KO     thrshld  score   E-value  KO definition\n* b0414            K01497  340.2    450.1   1.2e-130 GTP cyclohydrolase II";
        $this->assertSame(FormatDetector::FORMAT_KOFAMSCAN, $this->detector->detect($input));
    }

    public function testDetectKoala(): void
    {
        $input = "gene1\tK01497\ngene2\tK02858\ngene3\tK11752\ngene4\tK00794";
        $this->assertSame(FormatDetector::FORMAT_KOALA, $this->detector->detect($input));
    }

    public function testDetectGenericCsv(): void
    {
        $input = "gene,ko,product\nribA,K01497,GTP cyclohydrolase II\nribB,K02858,DHBP synthase";
        $this->assertSame(FormatDetector::FORMAT_CSV, $this->detector->detect($input));
    }
}
