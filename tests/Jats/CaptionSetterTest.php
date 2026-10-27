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

    public function testTableWithExistingLabelAndTitleReferenceOptionAndNoteAfter(): void
    {
        AbstractCaptionSetter::resetTitlePosition();

        // Exact scenario reported: table with existing label and caption,
        // setTitleReference=true inserts <p><xref ...></xref></p>, followed by Note.
        $xml = <<<XML
<article>
    <body>
        <table-wrap id="tbl1">
            <label>Table 1</label>
            <caption>
                <title>. Descriptive Statistics and Bivariate Correlations</title>
            </caption>
            <table>
                <tr><td>1</td></tr>
            </table>
        </table-wrap>
        <p>
            Note. ADSMU = average daily social media use, CO = cyberostracism, SE = self-esteem, PD = psychological distress, SMA = social media addiction, α = Cronbach’s alpha, ω = McDonald’s omega, * <italic>p</italic> &lt; .05, ** <italic>p</italic> &lt; .01.
        </p>
    </body>
</article>
XML;

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        ($this->tablesCaptionSetter)($xpath, true); // true = $setTitleReference

        // 1. Table must have <table-wrap-foot> containing the Note
        $footP = $xpath->query('//table-wrap/table-wrap-foot/p');
        $this->assertEquals(1, $footP->length, '<table-wrap-foot> must contain the note paragraph');
        $this->assertStringStartsWith('Note. ADSMU =', trim($footP->item(0)->textContent));

        // 2. The reference paragraph must remain in the body
        $bodyP = $xpath->query('/article/body/p');
        $this->assertEquals(1, $bodyP->length, 'Only the generated reference paragraph should remain in body');
        $xref = $xpath->query('/article/body/p/xref[@rid="tbl1"]');
        $this->assertEquals(1, $xref->length);
    }

    public function testTableWithTitleAfterAndTitleReferenceOptionAndNoteAfter(): void
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
        <p>Table 1: Descriptive Statistics and Bivariate Correlations</p>
        <p>
            Note. ADSMU = average daily social media use.
        </p>
    </body>
</article>
XML;

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        ($this->tablesCaptionSetter)($xpath, true); // true = $setTitleReference

        // 1. Table must have label, caption and table-wrap-foot
        $this->assertEquals('Table 1', $xpath->query('//table-wrap/label')->item(0)->textContent);
        $footP = $xpath->query('//table-wrap/table-wrap-foot/p');
        $this->assertEquals(1, $footP->length);
        $this->assertStringStartsWith('Note. ADSMU =', trim($footP->item(0)->textContent));

        // 2. The title paragraph was replaced by xref in body
        $bodyP = $xpath->query('/article/body/p');
        $this->assertEquals(1, $bodyP->length);
        $this->assertEquals(1, $xpath->query('/article/body/p/xref[@rid="tbl1"]')->length);
    }

    public function testTableFollowedByFigureDoNotCrossContaminateNotes(): void
    {
        AbstractCaptionSetter::resetTitlePosition();

        // Exact bug scenario: Table with title and note, followed by Figure with title and note.
        // FiguresCaptionSetter runs before TablesCaptionSetter in Automark.
        $xml = <<<XML
<article xmlns:xlink="http://www.w3.org/1999/xlink">
    <body>
        <p>Table 5: Model Fit Indices for the Full Sample and Gender Groups</p>
        <table-wrap id="tbl5">
            <table>
                <tr><td>Model 1</td><td>114.61</td></tr>
            </table>
        </table-wrap>
        <p>Note. Models 1, 2, and 3 were initially tested in the full sample.</p>
        <p>Figure 2: Standardized Path Estimates for the Mediation Model</p>
        <fig id="fig2">
            <graphic xlink:href="image2.png"/>
        </fig>
        <p>Note. The figure presents standardized estimates for the full sample.</p>
    </body>
</article>
XML;

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        // Run in the exact order as Automark: figures first, then tables
        ($this->figuresCaptionSetter)($xpath, true);
        ($this->tablesCaptionSetter)($xpath, true);

        // 1. Table 5 must have its own note in table-wrap-foot
        $tableFootP = $xpath->query('//table-wrap[@id="tbl5"]/table-wrap-foot/p');
        $this->assertEquals(1, $tableFootP->length, 'Table 5 must have exactly 1 note in table-wrap-foot');
        $this->assertStringStartsWith('Note. Models 1, 2, and 3', trim($tableFootP->item(0)->textContent));

        // 2. Figure 2 must have its own note in caption
        $figCaptionP = $xpath->query('//fig[@id="fig2"]/caption/p');
        $this->assertEquals(1, $figCaptionP->length, 'Figure 2 must have exactly 1 note in caption');
        $this->assertStringStartsWith('Note. The figure presents', trim($figCaptionP->item(0)->textContent));

        // 3. Ensure Table 5 note did NOT end up inside Figure 2
        $figAllText = $xpath->query('//fig[@id="fig2"]')->item(0)->textContent;
        $this->assertStringNotContainsString('Models 1, 2, and 3', $figAllText, 'Figure 2 must not contain Table 5 note');

        // 4. Ensure Figure 2 note did NOT end up inside Table 5
        $tableAllText = $xpath->query('//table-wrap[@id="tbl5"]')->item(0)->textContent;
        $this->assertStringNotContainsString('The figure presents', $tableAllText, 'Table 5 must not contain Figure 2 note');
    }

    public function testTableFollowedByFigureWithExistingLabelsDoNotCrossContaminateNotes(): void
    {
        AbstractCaptionSetter::resetTitlePosition();

        // Scenario where docx2jats already generated labels/captions, followed by notes in body
        $xml = <<<XML
<article xmlns:xlink="http://www.w3.org/1999/xlink">
    <body>
        <table-wrap id="tbl5">
            <label>Table 5</label>
            <caption>
                <title>. Model Fit Indices</title>
            </caption>
            <table>
                <tr><td>1</td></tr>
            </table>
        </table-wrap>
        <p>Note. Table 5 note explaining fit indices.</p>
        <fig id="fig2">
            <label>Figure 2</label>
            <caption>
                <title>. Path Estimates</title>
            </caption>
            <graphic xlink:href="image2.png"/>
        </fig>
        <p>Note. Figure 2 note explaining path estimates.</p>
    </body>
</article>
XML;

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        // Run figures first, then tables with setTitleReference = true
        ($this->figuresCaptionSetter)($xpath, true);
        ($this->tablesCaptionSetter)($xpath, true);

        // Table 5 note
        $tableFootP = $xpath->query('//table-wrap[@id="tbl5"]/table-wrap-foot/p');
        $this->assertEquals(1, $tableFootP->length);
        $this->assertEquals('Note. Table 5 note explaining fit indices.', trim($tableFootP->item(0)->textContent));

        // Figure 2 note
        $figCaptionP = $xpath->query('//fig[@id="fig2"]/caption/p');
        $this->assertEquals(1, $figCaptionP->length);
        $this->assertEquals('Note. Figure 2 note explaining path estimates.', trim($figCaptionP->item(0)->textContent));
    }

    public function testTwoConsecutiveTablesDoNotCrossContaminateNotes(): void
    {
        AbstractCaptionSetter::resetTitlePosition();

        $xml = <<<XML
<article>
    <body>
        <p>Table 1: First table title</p>
        <table-wrap id="tbl1">
            <table><tr><td>1</td></tr></table>
        </table-wrap>
        <p>Note. Note belonging to Table 1.</p>
        <p>Table 2: Second table title</p>
        <table-wrap id="tbl2">
            <table><tr><td>2</td></tr></table>
        </table-wrap>
        <p>Note. Note belonging to Table 2.</p>
    </body>
</article>
XML;

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        ($this->tablesCaptionSetter)($xpath, true);

        $tbl1Foot = $xpath->query('//table-wrap[@id="tbl1"]/table-wrap-foot/p');
        $this->assertEquals(1, $tbl1Foot->length);
        $this->assertEquals('Note. Note belonging to Table 1.', trim($tbl1Foot->item(0)->textContent));

        $tbl2Foot = $xpath->query('//table-wrap[@id="tbl2"]/table-wrap-foot/p');
        $this->assertEquals(1, $tbl2Foot->length);
        $this->assertEquals('Note. Note belonging to Table 2.', trim($tbl2Foot->item(0)->textContent));
    }

    public function testAutomarkDoesNotProcessTitlesOrNotesWhenDisabled(): void
    {
        $automark = self::getContainer()->get(\App\Service\Automark\App\Automark::class);

        $xml = <<<XML
<article xmlns:xlink="http://www.w3.org/1999/xlink">
    <body>
        <p>Table 5: Model Fit Indices</p>
        <table-wrap id="tbl5">
            <table><tr><td>1</td></tr></table>
        </table-wrap>
        <p>Note. Note for Table 5.</p>
        <p>Figure 2: Model Diagram</p>
        <fig id="fig2">
            <graphic xlink:href="image2.png"/>
        </fig>
        <p>Note. Note for Figure 2.</p>
    </body>
</article>
XML;

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);

        // Call automark with setFiguresTitles = false and setTablesTitles = false
        $automark(
            $dom,
            null,   // citationStyleName
            null,   // cslReferences
            false,  // generateBibliography
            false,  // generateBibliographyMixedCitations
            false,  // generateCitations
            false,  // setFiguresTitles (DISABLED)
            false,  // setTablesTitles (DISABLED)
            false   // replaceTitlesWithReferences
        );

        $xpath = new DOMXPath($dom);

        // Neither table nor figure should have titles or notes processed
        $this->assertEquals(0, $xpath->query('//table-wrap/table-wrap-foot')->length, 'No table-wrap-foot should be created when setTablesTitles is false');
        $this->assertEquals(0, $xpath->query('//table-wrap/label')->length);
        $this->assertEquals(0, $xpath->query('//fig/caption')->length, 'No fig caption should be created when setFiguresTitles is false');
        $this->assertEquals(0, $xpath->query('//fig/label')->length);

        // All 4 paragraphs must remain untouched in body
        $this->assertEquals(4, $xpath->query('/article/body/p')->length, 'All title and note paragraphs must remain intact in body');
    }
}

