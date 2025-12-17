<?php

namespace App\Tests\Jats;

use App\Service\Automark\Dom\JatsFlavourDetector;
use PHPUnit\Framework\TestCase;

class JatsFlavourDetectorTest extends TestCase
{
    public function testSomething(): void
    {
        $tests = [
            'jats' => [
                <<<XML
                    <!DOCTYPE article PUBLIC "-//NLM//DTD JATS (Z39.96) Journal Archiving DTD v1.1d3//EN" "JATS-archivearticle1.dtd">
                    <article xmlns:xlink="http://www.w3.org/1999/xlink" article-type="research-article">
                    </article>
                XML,
                <<<XML
                    <!DOCTYPE article PUBLIC "-//NLM//DTD JATS (Z39.96) Journal Publishing DTD v1.1 20151215//EN" "https://jats.nlm.nih.gov/publishing/1.1/JATS-journalpublishing1.dtd">
                    <article xmlns:xlink="http://www.w3.org/1999/xlink" xml:lang="en" dtd-version="1.1">
                    </article>
                XML,
            ],
            'scielo' => [
                <<<XML
                    <!DOCTYPE article PUBLIC "-//SciELO//DTD SciELO Publishing Schema Article v1.8//EN" "scielo-publishing-schema-1.8.dtd">
                    <article xmlns:xlink="http://www.w3.org/1999/xlink" article-type="research-article">
                    </article>
                XML,
                <<<XML
                    <!DOCTYPE article PUBLIC "-//NLM//DTD JATS (Z39.96) Journal Publishing DTD v1.1 20151215//EN" "https://jats.nlm.nih.gov/publishing/1.1/JATS-journalpublishing1.dtd">
                    <article xmlns:xlink="http://www.w3.org/1999/xlink" xml:lang="en" dtd-version="1.1" specific-use="sps-1.9">
                    </article>
                XML,
            ],
            'unknown' => [
                <<<XML
                    <!DOCTYPE article PUBLIC "-//SOMETHING//DTD SOMETHING Publishing Schema Article v1.8//EN" "something-publishing-schema-1.8.dtd">
                    <article xmlns:xlink="http://www.w3.org/1999/xlink" article-type="research-article">
                    </article>
                XML,
            ],
        ];

        $detector = new JatsFlavourDetector();

        foreach ($tests as $expectedFlavour => $xmls) {
            for ($i=0; $i < count($xmls); $i++) {
                $doc = new \DOMDocument('1.0', 'UTF-8');
                $doc->loadXML($xmls[$i]);

                $detectedFlavour = $detector($doc, 'unknown');

                $this->assertEquals($expectedFlavour, $detectedFlavour, "Expected: {$expectedFlavour}, Detected: {$detectedFlavour}, Test: {$i}");
            }
        }
    }
}
