<?php

namespace App\Service\Automark\Dom\CitationStyle;

use App\Service\Automark\Dom\CslRepositoryInterface;
use DOMDocument;
use DOMNode;
use SplObjectStorage;

interface SupportsNodeReplacementCitationsInterface extends CitationStyleInterface
{
    /**
     * @param DOMDocument                        $doc           The DOM document where the citations will be searched.
     * @param CslRepositoryInterface             $repository    Repository containing the preset CSL citation data.
     * @param DOMNode                            $node          The node in which to search for citations.
     * @param SplObjectStorage<DOMNode,DOMNode> &$replacements
     *      Reference to the map to be populated with DOM nodes and their replacements.
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
