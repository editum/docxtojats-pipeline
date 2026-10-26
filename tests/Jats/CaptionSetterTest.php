<?php

namespace App\Tests\Jats;

use App\Service\Automark\Dom\Caption\AbstractCaptionSetter;
use App\Service\Automark\Dom\Caption\FiguresCaptionSetter;
use App\Service\Automark\Dom\Caption\TablesCaptionSetter;
use App\Service\Automark\Inf\LookupFactory;
use DOMDocument;
use DOMXPath;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CaptionSetterTest extends KernelTestCase
{
    private TablesCaptionSetter $tablesCaptionSetter;
    private FiguresCaptionSetter $figuresCaptionSetter;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $keywordsDir = self::getContainer()->getParameter('automark.keywords.dir');
        $lookupTablesDir = self::getContainer()->getParameter('automark.lookup_tables.dir');

        $lookupFactory = new LookupFactory($keywordsDir, $lookupTablesDir);

        $tableKeywords = $lookupFactory->getTableKeywords();
        $figureKeywords = $lookupFactory->getFigureKeywords();
        $noteKeywords = $lookupFactory->getNoteKeywords();

        $this->tablesCaptionSetter = new TablesCaptionSetter($tableKeywords, $noteKeywords);
        $this->figuresCaptionSetter = new FiguresCaptionSetter($figureKeywords, $noteKeywords);
    }

    public function testTableWithTitleBeforeAndNoteAfter(): void
    {
        AbstractCaptionSetter::resetTitlePosition();

        $xml = <<<XML
<article>
    <body>
        <p>Tabla 1: Resultados del experimento.</p>
        <table-wrap id="tbl1">
            <table>
                <tr><td>1</td></tr>
            </table>
        </table-wrap>
        <p>Nota: Datos recopilados durante 2024.</p>
    </body>
</article>
XML;

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        ($this->tablesCaptionSetter)($xpath, false);

        // Verify title & label
        $this->assertEquals('Tabla 1', $xpath->query('//table-wrap/label')->item(0)->textContent);
        $this->assertEquals('Resultados del experimento', $xpath->query('//table-wrap/caption/title')->item(0)->textContent);

        // Verify table-wrap-foot
        $footNodes = $xpath->query('//table-wrap/table-wrap-foot');
        $this->assertEquals(1, $footNodes->length, '<table-wrap-foot> should exist inside <table-wrap>');

        $pInFoot = $xpath->query('//table-wrap/table-wrap-foot/p');
        $this->assertEquals(1, $pInFoot->length);
        $this->assertEquals('Nota: Datos recopilados durante 2024.', $pInFoot->item(0)->textContent);

        // Verify original paragraphs in body are removed
        $bodyParagraphs = $xpath->query('/article/body/p');
        $this->assertEquals(0, $bodyParagraphs->length, 'Original title and note paragraphs should have been moved/removed from body');
    }

    public function testTableWithTitleAfterAndNoteAfter(): void
    {
        AbstractCaptionSetter::resetTitlePosition();

        $xml = <<<XML
<article>
    <body>
        <table-wrap id="tbl1">
            <table>
                <tr><td>1</td></tr>
            </table>
        </table-wrap>
        <p>Tabla 1: Resultados del experimento.</p>
        <p>Nota. Valores expresados en porcentajes.</p>
    </body>
</article>
XML;

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        ($this->tablesCaptionSetter)($xpath, false);

        // Verify table-wrap-foot
        $footP = $xpath->query('//table-wrap/table-wrap-foot/p');
        $this->assertEquals(1, $footP->length);
        $this->assertEquals('Nota. Valores expresados en porcentajes.', $footP->item(0)->textContent);

        // Verify body paragraphs removed
        $this->assertEquals(0, $xpath->query('/article/body/p')->length);
    }

    public function testTableWithTitleBeforeAndSubtitleBefore(): void
    {
        AbstractCaptionSetter::resetTitlePosition();

        $xml = <<<XML
<article>
    <body>
        <p>Tabla 2: Crecimiento anual.</p>
        <p>Subtítulo: Desglose por regiones.</p>
        <table-wrap id="tbl2">
            <table>
                <tr><td>2</td></tr>
            </table>
        </table-wrap>
    </body>
</article>
XML;

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        ($this->tablesCaptionSetter)($xpath, false);

        $this->assertEquals('Tabla 2', $xpath->query('//table-wrap/label')->item(0)->textContent);
        $footP = $xpath->query('//table-wrap/table-wrap-foot/p');
        $this->assertEquals(1, $footP->length);
        $this->assertEquals('Subtítulo: Desglose por regiones.', $footP->item(0)->textContent);
    }

    public function testTableWithMultipleNotes(): void
    {
        AbstractCaptionSetter::resetTitlePosition();

        $xml = <<<XML
<article>
    <body>
        <p>Tabla 1: Estadísticas.</p>
        <table-wrap id="tbl1">
            <table>
                <tr><td>1</td></tr>
            </table>
        </table-wrap>
        <p>Nota 1: Primer grupo de control.</p>
        <p>Nota 2: Segundo grupo de control.</p>
    </body>
</article>
XML;

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        ($this->tablesCaptionSetter)($xpath, false);

        $footP = $xpath->query('//table-wrap/table-wrap-foot/p');
        $this->assertEquals(2, $footP->length, 'Both notes should be inside table-wrap-foot');
        $this->assertEquals('Nota 1: Primer grupo de control.', $footP->item(0)->textContent);
        $this->assertEquals('Nota 2: Segundo grupo de control.', $footP->item(1)->textContent);
    }

    public function testTableIgnoresNonNoteWords(): void
    {
        AbstractCaptionSetter::resetTitlePosition();

        $xml = <<<XML
<article>
    <body>
        <p>Tabla 1: Datos.</p>
        <table-wrap id="tbl1">
            <table>
                <tr><td>1</td></tr>
            </table>
        </table-wrap>
        <p>Notablemente, los efectos observados fueron estadísticamente significativos.</p>
    </body>
</article>
XML;

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        ($this->tablesCaptionSetter)($xpath, false);

        // table-wrap-foot should NOT be created
        $this->assertEquals(0, $xpath->query('//table-wrap/table-wrap-foot')->length);

        // Body paragraph should still remain
        $bodyP = $xpath->query('/article/body/p');
        $this->assertEquals(1, $bodyP->length);
        $this->assertStringStartsWith('Notablemente', $bodyP->item(0)->textContent);
    }

    public function testFigureWithNoteAttachedToCaption(): void
    {
        AbstractCaptionSetter::resetTitlePosition();

        $xml = <<<XML
<article xmlns:xlink="http://www.w3.org/1999/xlink">
    <body>
        <p>Figura 1: Evolución temporal.</p>
        <fig id="fig1">
            <graphic xlink:href="image1.png"/>
        </fig>
        <p>Nota: Elaboración propia a partir de datos oficiales.</p>
    </body>
</article>
XML;

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        ($this->figuresCaptionSetter)($xpath, false);

        $this->assertEquals('Figura 1', $xpath->query('//fig/label')->item(0)->textContent);
        $this->assertEquals('Evolución temporal', $xpath->query('//fig/caption/title')->item(0)->textContent);

        // In fig, note goes into caption/p according to JATS DTD
        $captionP = $xpath->query('//fig/caption/p');
        $this->assertEquals(1, $captionP->length, '<fig> note should be inside <caption><p>');
        $this->assertEquals('Nota: Elaboración propia a partir de datos oficiales.', $captionP->item(0)->textContent);

        // Body paragraph removed
        $this->assertEquals(0, $xpath->query('/article/body/p')->length);
    }

    public function testTableWithInlineFormattingInNote(): void
    {
        AbstractCaptionSetter::resetTitlePosition();

        $xml = <<<XML
<article>
    <body>
        <p>Tabla 1: Resultados.</p>
        <table-wrap id="tbl1">
            <table>
                <tr><td>1</td></tr>
            </table>
        </table-wrap>
        <p><italic>Nota.</italic> * <italic>p</italic> &lt; 0.05.</p>
    </body>
</article>
XML;

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        ($this->tablesCaptionSetter)($xpath, false);

        $footP = $xpath->query('//table-wrap/table-wrap-foot/p');
        $this->assertEquals(1, $footP->length);
        $this->assertEquals('Nota. * p < 0.05.', $footP->item(0)->textContent);

        // Verify inline italic tags were preserved
        $italics = $xpath->query('//table-wrap/table-wrap-foot/p/italic');
        $this->assertEquals(2, $italics->length, 'Inline <italic> tags should be preserved inside <table-wrap-foot>');
    }

    public function testTableWithExistingLabelAndNote(): void
    {
        AbstractCaptionSetter::resetTitlePosition();

        $xml = <<<XML
<article>
    <body>
        <table-wrap id="tbl1">
            <label>Table 1</label>
            <caption>
                <title>Pre-existing table title</title>
            </caption>
            <table>
                <tr><td>1</td></tr>
            </table>
        </table-wrap>
        <p>Note: Calculated using standard deviation.</p>
    </body>
</article>
XML;

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        ($this->tablesCaptionSetter)($xpath, false);

        $footP = $xpath->query('//table-wrap/table-wrap-foot/p');
        $this->assertEquals(1, $footP->length);
        $this->assertEquals('Note: Calculated using standard deviation.', $footP->item(0)->textContent);
        $this->assertEquals(0, $xpath->query('/article/body/p')->length);
    }
}
