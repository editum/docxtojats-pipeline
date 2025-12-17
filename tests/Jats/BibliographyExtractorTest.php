<?php

namespace App\Tests\Jats;

use App\Service\Automark\Dom\BibliographyTextExtractor;
use App\Service\Automark\Inf\LookupFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BibliographyExtractorTest extends KernelTestCase

{
    public function testInvokeExtractsBibliography()
    {
        $xml = <<<XML
<article xmlns:xlink="http://www.w3.org/1999/xlink">
    <body>
        <sec id="sec1">
            <title>Introducción</title>
            <p>Lorem ipsum dolor sit amet, <xref ref-type="bibr" rid="R1"/> (Smith, 2021) consectetur adipiscing elit.</p>
            <p>Phasellus <xref ref-type="bibr" rid="R2"/> (Doe, 2020) vehicula nunc.</p>
            <p>Maecenas <xref ref-type="bibr" rid="R3"/> (Brown, 2019) sit amet purus.</p>
        </sec>
        <sec id="sec2">
            <title>Notas y curiosidades</title>
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
        </sec>
        <sec id="sec3">
            <title>Referencias</title>
            <p>[R1] Smith J. Lorem Ipsum Study. J Test Sci. 2021.</p>
            <p>[R2] Doe A. Another Study. Test Journal. 2020. <ext-link ext-link-type="uri" xlink:href="http://www.example.com">http://www.example.com</ext-link></p>
            <p>[R3] Brown B. <i>Book of Lorem</i>. 2019. Gotham City: Ipsum Press. DOI: <ext-link ext-link-type="uri" xlink:href="https://doi.org/10.1234/abcd.2019.001">10.1234/abcd.2019.001</ext-link></p>
            <p>[R4] White C. Lorem Thesis. 2018.</p>
            <p>[R4] Lorem &amp; Ipsum. 2066.</p>
        </sec>
        <sec id="sec4">
            <title>Conclusiones</title>
            <p>Lorem ipsum dolor sit amet.</p>
        </sec>
    </body>
</article>
XML;

        $expected = <<<'TEXT'
REFERENCIAS

[R1] Smith J. Lorem Ipsum Study. J Test Sci. 2021.

[R2] Doe A. Another Study. Test Journal. 2020. http://www.example.com .

[R3] Brown B. Book of Lorem. 2019. Gotham City: Ipsum Press. DOI: 10.1234/abcd.2019.001 .

[R4] White C. Lorem Thesis. 2018.

[R4] Lorem & Ipsum. 2066.

========

CONCLUSIONES

Lorem ipsum dolor sit amet.
TEXT;

        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->loadXML($xml);
        $doc->normalize();

        self::bootKernel();

        $keywordsDir = self::getContainer()->getParameter('automark.keywords.dir');
        $lookupTablesDir = self::getContainer()->getParameter('automark.lookup_tables.dir');

        $keywordsFactory = new LookupFactory($keywordsDir, $lookupTablesDir);
        $keywords = $keywordsFactory->getBibliographyKeywords();

        $extractor = new BibliographyTextExtractor($keywords);

        // Invocar el extractor
        $result = $extractor($doc);

        //file_put_contents('var/data/a.txt', $result);

        dump(compact('result', 'expected'));

        $this->assertIsString($result, 'Bibliography should be a string');

        $this->assertNotEmpty(trim($result), 'Bibliography should no be empty');

        $this->assertEquals($expected, $result);
    }
}

