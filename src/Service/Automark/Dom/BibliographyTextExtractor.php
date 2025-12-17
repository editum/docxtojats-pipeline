<?php

namespace App\Service\Automark\Dom;

use DOMElement;
use DOMNode;
use DOMXPath;

class BibliographyTextExtractor
{
    const UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZÁÉÍÓÚÄËÏÖÜÀÈÌÒÙÂÊÎÔÛÇÑ';
    const LOWER = 'abcdefghijklmnopqrstuvwxyzáéíóúäëïöüàèìòùâêîôûçñ';

    /** @var array preprocessed keywords */
    private array $keywords;

    /**
     * @param array<string> $keywords Keywords used to locate sections whose titles contain any of them.
     */
    public function __construct(array $keywords)
    {
        $this->keywords = $keywords;
    }

    /**
     * Searchs for sections containing the bibliography using a list of keywords for the title.
     * Return a string with the content of the candidate sections separated by new line.
     *
     * @param \DOMDocument the jats document
     * @return ?string with the text of all posible sections
     */
    public function __invoke(\DOMDocument $doc): ?string
    {
        // Construct the section query for titles with the defined keywords
        $containsParts = array_map(function($kw) {
            return sprintf("contains(translate(title[1], '%s', '%s'), '%s')",
                self::UPPER,
                self::LOWER,
                $kw
            );
        }, $this->keywords);
        $containsExpr = implode(' or ', $containsParts);
        $query = "//article/body//sec[$containsExpr]";

        // Process the sections
        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query($query);
        $secs = [];
        foreach ($nodes as $sec) {
            if ($text = $this->stringify($sec)) {
                if (!empty($secs)) {
                    $secs[] = "========";
                }
                $secs[] = $text;
                $secs[] = "\n";
            }
        }

        $text =  preg_replace("/\n{3,}/", "\n\n", trim(implode("\n", $secs)));

        return $text ?: null;
    }

    private function stringify(DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            switch ($child->nodeType) {
                case XML_TEXT_NODE:
                    $text .= $child->nodeValue;
                    break;

                case XML_ELEMENT_NODE:
                    // REVIEW what about <td> ?
                    /** @var DOMElement $child */
                    switch ($child->nodeName) {
                        case 'title':
                            $text .= "\n\n" . mb_strtoupper(trim($this->stringify($child))) . "\n\n";
                            break;
                        case 'p':
                            $t = trim($this->stringify($child));
                            if ($t) {
                                if (substr($t, -1) !== '.') {
                                    $t .= ' .';
                                }
                                $text .= $t . "\n\n";
                            }
                            break;
                        case 'br':
                            $text .= "\n";
                            break;
                        case 'ext-link':
                            $t = $child->textContent ?: $child->getAttribute('xlink:href');
                            if ($t !== '') {
                                if (substr($text, -1) !== ' ' && substr($text, -1) !== "\n") {
                                    $text .= ' ';
                                }
                                $text .= $t;
                            }
                            break;
                        default:
                            $text .= $this->stringify($child);
                            break;
                    }
                    break;
            }
        }
        return $text;
    }
}
