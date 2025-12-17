<?php

namespace App\Service\Automark\Dom\CitationStyle;

use App\Service\Automark\Dom\CslRepositoryInterface;
use DOMDocument;

interface SupportPresetCitationsInterface extends CitationStyleInterface
{
    /**
    * Builds a map of citation text strings to DOM replacement XREF nodes from preset CSL
    * references.
    *
    * Iterates through all citation presets in the given repository and populates the $replacements
    * array with the corresponding XREF nodes used for subsitution.
    *
    * @param DOMDocument             $doc           The DOM document where the citations will be searched.
    * @param CslRepositoryInterface  $repository    Repository containing the preset CSL citation data.
    * @param array<string,DOMNode>  &$replacements  Reference array to be populated with citation text
    *                                               as keys and replacement DOM nodes as values.
    *
    * @return void
    */
    public function buildCitationReferenceMapFromPresets(
        DOMDocument $doc,
        CslRepositoryInterface $repository,
        array &$replacements
    ): void;
}
