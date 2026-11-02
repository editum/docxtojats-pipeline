<?php

namespace App\Tests\Jats;

use App\Service\DocConversion\Dom\DocxToJats;
use PHPUnit\Framework\TestCase;

class TableConversionTest extends TestCase
{
    public function testTableCellsPreserveInlineStylesAndNegativeNumbers(): void
    {
        $fixturePath = __DIR__ . '/../fixtures/table_with_formatting.docx';
        $this->assertFileExists($fixturePath);

        $docxToJats = new DocxToJats($fixturePath);
        $xpath = $docxToJats->jatsXpath;

        // Verify mixed inline style header: Female (n = 279)
        $femaleCells = $xpath->query('//td[contains(., "Female (")]');
        $this->assertEquals(1, $femaleCells->count(), 'Should find 1 Female header cell');
        $femaleCell = $femaleCells->item(0);
        $this->assertStringContainsString('Female (', $femaleCell->textContent);
        $this->assertStringContainsString(' = 279)', $femaleCell->textContent);
        $italicN = $xpath->query('./italic', $femaleCell);
        $this->assertEquals(1, $italicN->count(), 'Should contain an italic element');
        $this->assertEquals('n', $italicN->item(0)->textContent);

        // Verify Cohen's d header
        $cohenCells = $xpath->query('//td[contains(., "Cohen")]');
        $this->assertEquals(1, $cohenCells->count(), 'Should find 1 Cohen header cell');
        $cohenCell = $cohenCells->item(0);
        $italicD = $xpath->query('./italic', $cohenCell);
        $this->assertEquals(1, $italicD->count(), 'Should contain an italic element for d');
        $this->assertEquals('d', $italicD->item(0)->textContent);

        // Verify negative values are not truncated to just a dash
        $negative201 = $xpath->query('//td[contains(., "2.01")]');
        $this->assertEquals(1, $negative201->count(), 'Cell with -2.01 should exist');
        $this->assertMatchesRegularExpression('/[−\-]\s*2\.01/', $negative201->item(0)->textContent);

        $negative177 = $xpath->query('//td[contains(., ".177")]');
        $this->assertEquals(1, $negative177->count(), 'Cell with -.177 should exist');
        $this->assertMatchesRegularExpression('/[−\-]\s*\.177/', $negative177->item(0)->textContent);

        $negative139 = $xpath->query('//td[contains(., "1.39")]');
        $this->assertEquals(1, $negative139->count(), 'Cell with -1.39 should exist');
        $this->assertMatchesRegularExpression('/[−\-]\s*1\.39/', $negative139->item(0)->textContent);

        $negative016 = $xpath->query('//td[contains(., "0.16")]');
        $this->assertEquals(1, $negative016->count(), 'Cell with -0.16 should exist');
        $this->assertMatchesRegularExpression('/[−\-]\s*0\.16/', $negative016->item(0)->textContent);
    }
}
