<?php

namespace App\Service\Automark\Dom\CitationStyle;

use App\Component\Data\PropertyAccess;
use App\Service\Automark\Dom\CslRepositoryInterface;
use DOMDocument;
use stdClass;

trait CitationPresetTrait
{
    /**
    * Adds a citation reference node to the replacements map.
    *
    * Given a plain-text citation string and its CSL metadata, this method
    * builds the corresponding <xref> node and registers it in the replacements array
    * so it can be substituted later in the DOM.
    *
    * @param DOMDocument $doc           The target DOM document where the citation will be used.
    * @param string      $plaincit      The plain-text citation to be matched in the document.
    * @param stdClass    $csl           The CSL object containing citation metadata.
    * @param array       &$replacements Reference array where the label-to-node mapping is stored.
    *
    * @return void
    */
    protected function addCitationReference(DOMDocument $doc, string $plaincit, stdClass $csl, array &$replacements): void
    {
        if (! in_array($plaincit, $csl->{CslRepositoryInterface::CITATIONS})) {
            $csl->{CslRepositoryInterface::CITATIONS}[] = $plaincit;
        }

        $rid = $csl->{CslRepositoryInterface::ID};
        $xrefNode = $this->createRefNode($doc, $rid, $plaincit);
        $replacements[$plaincit] = $xrefNode;
    }

    public function buildCitationReferenceMapFromPresets(
        DOMDocument $doc,
        CslRepositoryInterface $repository,
        array &$replacements
    ): void {
        foreach ($repository->getAll() as $csl) {
            foreach (PropertyAccess::getValue($csl, CslRepositoryInterface::CITATIONS, []) as $citation) {
                if (! $citation = @trim($citation))
                    continue;
                $this->addCitationReference($doc, $citation, $csl, $replacements);
            }
        }
    }
}
