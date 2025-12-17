<?php

namespace App\Service\Automark\Dom;

use App\Service\Automark\Dom\CitationStyle\CitationStyleInterface;
use App\Service\Automark\Dom\CitationStyle\SupportPresetCitationsInterface;
use App\Service\Automark\Dom\CitationStyle\SupportsNodeReplacementCitationsInterface;
use DOMDocument;
use DOMNode;
use DOMXPath;
use SplObjectStorage;

final class CitationGenerator
{
    /**
     * Generate a list of citations and their replacement xref node.
     *
     * @param DOMDocument             $doc         The DOM document where the citations will be searched.
     * @param DOMXPath                $xpath
     * @param CslRepositoryInterface  $repository  Repository containing the preset CSL citation data.
     * @param CitationStyleInterface  $style       The citation language style used.
     * 
     * @return array{0:array<string,DOMNode>,1:SplObjectStorage<DOMNode, DOMNode>}
     *      Two list, the first contains text replacements with nodes and the second node replacements.
    */
    public function __invoke(
        DOMDocument &$dom,
        DOMXPath $xpath,
        CslRepositoryInterface $cslRepository,
        CitationStyleInterface $style
    ): array {
        // Nothing to do
        if ($cslRepository->isEmpty()) {
            return [];
        }

        /** @var array<string,DOMNode> for text substitution with nodes */
        $textReplacements = [];
        /** @var SplObjectStorage<DOMNode,DOMNode> for node replacement */
        $nodeReplacements = new SplObjectStorage();

        // Add the preset citations if the style supports it
        if ($style instanceof SupportPresetCitationsInterface) {
            $style->buildCitationReferenceMapFromPresets($dom, $cslRepository, $textReplacements);
        }

        // Detect citations and add them to the map

        if ($style instanceof SupportsNodeReplacementCitationsInterface) {
            $replacements = &$nodeReplacements;
        } else {
            $replacements = &$textReplacements;
        }

        $query = $style->getXpathQuery();
        foreach ($xpath->query($query) as $node) {
            $style->buildCitationReferenceMapFromDetected($dom, $cslRepository, $node, $replacements);
        }

        return [$textReplacements, $nodeReplacements];
    }
}
