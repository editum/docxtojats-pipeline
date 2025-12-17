<?php

namespace App\Service\Automark\Dom\CitationStyle;

use App\Service\Automark\Dom\CslRepositoryInterface;
use DOMDocument;
use DOMNode;
use Psr\Log\LoggerInterface;

interface CitationStyleInterface
{
    /**
     * Creates a new object.
     * Remember to set the logger.
     */
    static public function create(): self;

    /**
     * The style long name.
     */
    static public function displayName(): string;

    /**
     * The name that identifies the style.
     */
    static public function name(): string;

    /**
     * Generates a new id based in the references.
     *
     * @param string $plainreference
     * @return mixed the new id
     */
    public function generateId(string $plainreference);

    public function setLogger(LoggerInterface $logger): void;

    /**
     * Returns the xpath search that will be performed by the CitationGenerator.
     * By default '/article/body//p'.
     * @return string xpath query.
     */
    public function getXpathQuery(): string;

    /**
     * Builds a map of citation text strings to DOM replacement XREF nodes from preset CSL references.
     *
     * Tries to find citations in the given node that are present in the repository, and populates the
     * $replacements array (or SplObjectStorage) with the corresponding XREF DOMNodes to be used for substitution.
     *
     * @param DOMDocument             $doc           The DOM document where the citations will be searched.
     * @param CslRepositoryInterface  $repository    Repository containing the preset CSL citation data.
     * @param DOMNode                 $node          The node in which to search for citations.
     * @param array<string,DOMNode>  &$replacements
     *     Reference to the map to be populated with citation text as keys and replacement DOM nodes as values.
     *
     * @return void
     */
    public function buildCitationReferenceMapFromDetected(
        DOMDocument $doc,
        CslRepositoryInterface $repository,
        DOMNode $node,
        &$replacements
    ): void;
}
