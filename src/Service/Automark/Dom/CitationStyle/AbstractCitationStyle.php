<?php

namespace App\Service\Automark\Dom\CitationStyle;

use DOMDocument;
use DOMElement;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract class AbstractCitationStyle implements CitationStyleInterface, LoggerAwareInterface
{
    const NAME                      = 'undefined';
    const DISPLAY_NAME              = 'undefined';
    const DOM_XPATH_QUERY           = '/article/body//p | /article/body//td[not(p)]';

    protected LoggerInterface $logger;

    static public function create(): self
    {
        return new static();
    }

    static public function displayName(): string
    {
        return static::DISPLAY_NAME;
    }

    static public function name(): string
    {
        return strtolower(static::NAME);
    }

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * Creates an <xref> element for a given reference ID and label.
     *
     * The resulting node will have the appropriate attributes and label text,
     * and will belong to the given DOMDocument.
     *
     * @param DOMDocument $doc   The DOM document to which the node will belong.
     * @param string      $rid   The reference ID (value for the `rid` attribute).
     * @param string      $label The human-readable label to use as the node's text content.
     *
     * @return DOMElement The created <xref> element.
     */
    protected function createRefNode(DOMDocument $doc, string $rid, string $label): DOMElement
    {
        $xrefNode = $doc->createElement('xref');
        $xrefNode->setAttribute('ref-type', 'bibr');
        $xrefNode->setAttribute('rid', 'bib'.$rid);
        $xrefNode->appendChild($doc->createTextNode($label));
        return $xrefNode;
    }

    public function getXpathQuery(): string
    {
        return static::DOM_XPATH_QUERY;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
