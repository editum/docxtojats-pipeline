<?php

namespace App\Tests\Util;

use App\Component\Xml\XmlUtils;
use DOMDocument;
use DOMElement;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class XmlNodeReplacementTest extends TestCase
{
    private function createNode(DOMDocument $doc, string $text): DOMElement
    {
        $el = $doc->createElement('xref');
        $el->nodeValue = $text;
        return $el;
    }

    private function runReplace(
        \DOMDocument $doc,
        array $replacements,
        bool $caseInsensitive,
        bool $wordBoundary
    ): void {
        $query = '//p//text()[not(ancestor::xref)]';

        if ($caseInsensitive)
            XmlUtils::iReplaceTextWithNodes($doc, $query, $replacements, $wordBoundary);
        else
            XmlUtils::replaceTextWithNodes($doc, $query, $replacements, $wordBoundary);
    }

    public function testSimpleReplacement()
    {
        $caseInsensitive = false;
        $wordBoundary = false;
        $xml = <<<XML
            <body>
                <p>Hola Figura 1</p>
                <p>Hola figura 1</p>
                <p>Hola Figura 10</p>
            </body>
            XML;
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($xml);

        $this->runReplace($doc, [
            'Figura 1' => $this->createNode($doc, 'Figura 1'),
        ], $caseInsensitive, $wordBoundary);

        $result = $doc->saveXML();
        $expect = <<<XML
            <?xml version="1.0"?>
            <body>
                <p>Hola <xref>Figura 1</xref></p>
                <p>Hola figura 1</p>
                <p>Hola <xref>Figura 1</xref>0</p>
            </body>
            XML;

        //print_r($result);

        $this->assertXmlStringEqualsXmlString($expect, $result);
    }


    public function testSimpleReplacementCaseInsensitive()
    {
        $caseInsensitive = true;
        $wordBoundary = false;
        $xml = <<<XML
            <body>
                <p>Hola Figura 1</p>
                <p>Hola figura 1</p>
                <p>Hola Figura 10</p>
            </body>
            XML;
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($xml);

        $this->runReplace($doc, [
            'Figura 1' => $this->createNode($doc, 'Figura 1'),
        ], $caseInsensitive, $wordBoundary);

        $result = $doc->saveXML();
        $expect = <<<XML
            <?xml version="1.0"?>
            <body>
                <p>Hola <xref>Figura 1</xref></p>
                <p>Hola <xref>figura 1</xref></p>
                <p>Hola <xref>Figura 1</xref>0</p>
            </body>
            XML;

        //print_r($result);

        $this->assertXmlStringEqualsXmlString($expect, $result);
    }

    public function testSimpleReplacementWithBoundaries()
    {
        $caseInsensitive = false;
        $wordBoundary = true;
        $xml = <<<XML
            <body>
                <p>Hola Figura 1</p>
                <p>Hola figura 1</p>
                <p>Hola Figura 10</p>
            </body>
            XML;
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($xml);

        $this->runReplace($doc, [
            'Figura 1' => $this->createNode($doc, 'Figura 1'),
        ], $caseInsensitive, $wordBoundary);

        $result = $doc->saveXML();
        $expect = <<<XML
            <?xml version="1.0"?>
            <body>
                <p>Hola <xref>Figura 1</xref></p>
                <p>Hola figura 1</p>
                <p>Hola Figura 10</p>
            </body>
            XML;

        //print_r($result);

        $this->assertXmlStringEqualsXmlString($expect, $result);
    }

    public function testSimpleReplacementCaseInsensitiveWithBoundaries()
    {
        $caseInsensitive = true;
        $wordBoundary = true;
        $xml = <<<XML
            <body>
                <p>Hola Figura 1</p>
                <p>Hola figura 1</p>
                <p>Hola Figura 10</p>
            </body>
            XML;
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($xml);

        $this->runReplace($doc, [
            'Figura 1' => $this->createNode($doc, 'Figura 1'),
        ], $caseInsensitive, $wordBoundary);

        $result = $doc->saveXML();
        $expect = <<<XML
            <?xml version="1.0"?>
            <body>
                <p>Hola <xref>Figura 1</xref></p>
                <p>Hola <xref>figura 1</xref></p>
                <p>Hola Figura 10</p>
            </body>
            XML;

        //print_r($result);

        $this->assertXmlStringEqualsXmlString($expect, $result);
    }

    public function testMultipleReplacementCaseInsensitiveWithBoundaries()
    {
        $caseInsensitive = true;
        $wordBoundary = true;
        $xml = <<<XML
            <body>
                <p>Hola Figura 1 y Figura 2</p>
                <p>Hola figura 1 y Figura 10.</p>
                <p>-Figura 10.</p>
            </body>
            XML;
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($xml);

        $this->runReplace($doc, [
            'Figura 1' => $this->createNode($doc, 'Figura 1'),
            'Figura 10' => $this->createNode($doc, 'figura 10'),
        ], $caseInsensitive, $wordBoundary);

        $result = $doc->saveXML();
        $expect = <<<XML
            <?xml version="1.0"?>
            <body>
                <p>Hola <xref>Figura 1</xref> y Figura 2</p>
                <p>Hola <xref>figura 1</xref> y <xref>Figura 10</xref>.</p>
                <p>-<xref>Figura 10</xref>.</p>
            </body>
            XML;

        //print_r($result);

        $this->assertXmlStringEqualsXmlString($expect, $result);
    }
}
