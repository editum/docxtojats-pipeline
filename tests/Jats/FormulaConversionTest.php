<?php

namespace App\Tests\Jats;

use App\Service\DocConversion\Dom\DocxToJats;
use docx2jats\jats\Formula as JatsFormula;
use DOMDocument;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class FormulaConversionTest extends TestCase
{
    private ?string $tmpDocx = null;

    protected function tearDown(): void
    {
        if ($this->tmpDocx && file_exists($this->tmpDocx)) {
            @unlink($this->tmpDocx);
        }
    }

    public function testExtractsInlineAndDisplayFormulasAsMathML(): void
    {
        $this->tmpDocx = tempnam(sys_get_temp_dir(), 'formula_test_') . '.docx';
        $this->createSampleFormulaDocx($this->tmpDocx);

        $docxToJats = new DocxToJats($this->tmpDocx);
        $xpath = $docxToJats->jatsXpath;
        $xpath->registerNamespace('mml', 'http://www.w3.org/1998/Math/MathML');

        // 1. Check inline-formula inside paragraph
        $inlineFormulas = $xpath->query('//body//p/inline-formula/mml:math');
        $this->assertEquals(1, $inlineFormulas->count(), 'Should extract 1 inline-formula with mml:math');

        $inlineMath = $inlineFormulas->item(0);
        $this->assertEquals(1, $xpath->query('.//mml:msup', $inlineMath)->count(), 'Inline formula should contain mml:msup');
        $this->assertEquals('x', $xpath->query('.//mml:msup//mml:mi', $inlineMath)->item(0)->textContent);
        $this->assertEquals('2', $xpath->query('.//mml:msup//mml:mn', $inlineMath)->item(0)->textContent);

        // 2. Check disp-formula with fraction, radical, and n-ary summation and trailing paragraph label (1)
        $dispFormulas = $xpath->query('//body//disp-formula');
        $this->assertEquals(3, $dispFormulas->count(), 'Should extract 3 disp-formula elements');

        $firstDisp = $dispFormulas->item(0);
        $this->assertEquals('e1', $firstDisp->getAttribute('id'));
        $this->assertEquals('(1)', $xpath->evaluate('string(label)', $firstDisp));
        $this->assertEquals(1, $xpath->query('.//mml:mfrac', $firstDisp)->count(), 'First disp-formula should contain mml:mfrac');
        $this->assertEquals(1, $xpath->query('.//mml:msqrt', $firstDisp)->count(), 'First disp-formula should contain mml:msqrt');
        $this->assertEquals(1, $xpath->query('.//mml:munderover', $firstDisp)->count(), 'First disp-formula should contain mml:munderover');

        // 3. Check pre-subscript (<m:sPre>) keeps empty <mml:mprescripts/> and empty <mml:mrow/> intact after cleanContent()
        $secondDisp = $dispFormulas->item(1);
        $this->assertEquals('e2', $secondDisp->getAttribute('id'));
        $this->assertEquals(1, $xpath->query('.//mml:mmultiscripts/mml:mprescripts', $secondDisp)->count(), 'Empty mml:mprescripts must not be stripped by cleanContent');
        $this->assertEquals(3, $xpath->query('.//mml:mmultiscripts/mml:mrow', $secondDisp)->count(), 'Empty mml:mrow in mmultiscripts must not be stripped by cleanContent');

        // 4. Check 1-row 2-cell equation layout table is converted to disp-formula with <label>(3)</label> instead of table-wrap
        $thirdDisp = $dispFormulas->item(2);
        $this->assertEquals('e3', $thirdDisp->getAttribute('id'));
        $this->assertEquals('(3)', $xpath->evaluate('string(label)', $thirdDisp));
        $this->assertEquals(0, $xpath->query('//body//table-wrap')->count(), 'Equation layout table must not produce a table-wrap');
    }

    public function testFallbackDomConverterMatchesXsltStructure(): void
    {
        $ommlXml = <<<'XML'
        <m:oMath xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">
            <m:sSub>
                <m:e><m:r><m:t>r</m:t></m:r></m:e>
                <m:sub><m:r><m:t>in</m:t></m:r></m:sub>
            </m:sSub>
            <m:r><m:t>=</m:t></m:r>
            <m:f>
                <m:num><m:r><m:t>a</m:t></m:r></m:num>
                <m:den>
                    <m:rad>
                        <m:radPr><m:degHide m:val="1"/></m:radPr>
                        <m:deg/>
                        <m:e><m:r><m:t>b</m:t></m:r></m:e>
                    </m:rad>
                </m:den>
            </m:f>
        </m:oMath>
        XML;

        $srcDom = new DOMDocument();
        $srcDom->loadXML($ommlXml);

        $targetDom = new DOMDocument();
        $mathNode = JatsFormula::convertOmmlNodeFallback($srcDom->documentElement, $targetDom);
        $this->assertNotNull($mathNode);

        $targetDom->appendChild($mathNode);
        $xmlOutput = $targetDom->saveXML($mathNode);

        $this->assertStringContainsString('<mml:msub>', $xmlOutput);
        $this->assertStringContainsString('<mml:mi>i</mml:mi><mml:mi>n</mml:mi>', $xmlOutput);
        $this->assertStringContainsString('<mml:mfrac>', $xmlOutput);
        $this->assertStringContainsString('<mml:msqrt>', $xmlOutput);
    }

    private function createSampleFormulaDocx(string $path): void
    {
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">'
            . '<w:body>'
            . '<w:p>'
            . '<w:r><w:t>Inline formula </w:t></w:r>'
            . '<m:oMath><m:sSup><m:e><m:r><m:t>x</m:t></m:r></m:e><m:sup><m:r><m:t>2</m:t></m:r></m:sup></m:sSup></m:oMath>'
            . '<w:r><w:t> in text.</w:t></w:r>'
            . '</w:p>'
            . '<w:p>'
            . '<m:oMathPara><m:oMath>'
            . '<m:nary><m:naryPr><m:chr m:val="∑"/><m:limLoc m:val="undOvr"/></m:naryPr>'
            . '<m:sub><m:r><m:t>i=1</m:t></m:r></m:sub>'
            . '<m:sup><m:r><m:t>n</m:t></m:r></m:sup>'
            . '<m:e><m:f><m:num><m:r><m:t>1</m:t></m:r></m:num><m:den><m:rad><m:radPr><m:degHide m:val="1"/></m:radPr><m:deg/><m:e><m:r><m:t>x</m:t></m:r></m:e></m:rad></m:den></m:f></m:e>'
            . '</m:nary>'
            . '</m:oMath></m:oMathPara>'
            . '<w:r><w:t>(1)</w:t></w:r>'
            . '</w:p>'
            . '<w:p>'
            . '<m:oMathPara><m:oMath>'
            . '<m:sPre><m:sub><m:r><m:t>0</m:t></m:r></m:sub><m:sup/><m:e><m:r><m:t>F</m:t></m:r></m:e></m:sPre>'
            . '</m:oMath></m:oMathPara>'
            . '</w:p>'
            . '<w:tbl><w:tr>'
            . '<w:tc><w:p><m:oMathPara><m:oMath><m:r><m:t>a=b</m:t></m:r></m:oMath></m:oMathPara></w:p></w:tc>'
            . '<w:tc><w:p><w:r><w:t>(3)</w:t></w:r></w:p></w:tc>'
            . '</w:tr></w:tbl>'
            . '</w:body>'
            . '</w:document>';

        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();
    }
}
