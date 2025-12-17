<?php

namespace App\Service\Automark\Inf\CitationStyle;

use App\Service\Automark\Dom\CitationStyle\AbstractCitationStyle;
use App\Service\Automark\Dom\CitationStyle\CitationStyleInterface;
use App\Service\Automark\Dom\CslRepositoryInterface;
use DOMDocument;
use DOMNode;

/**
 * Citation Style
 * Vancouver
 */
final class Vancouver extends AbstractCitationStyle
{
    const DEBUG = true;

    const NAME                  = 'vancouver';
    const DISPLAY_NAME          = 'Vancouver';

    //const PATTERN_CITATION = '/\(((?:(?:\d+|\d+[-–]\d+),\s?)*(?:\d+|\d+[-–]\d+))\)/u';
    const PATTERN_CITATION = '/\(((?:\d+(?:[-–]\d+)?)(?:,\d+(?:[-–]\d+)?)*)\)/u';

    public function generateId(string $plainReference)
    {
        preg_match('/^\d+/', $plainReference, $matches);
        return $matches[0] ?? 0;
    }

    public function buildCitationReferenceMapFromDetected(
        DOMDocument $doc,
        CslRepositoryInterface $repository,
        DOMNode $node,
        &$replacements
    ): void {
        $text = $node->textContent;

        // Find posible citations (anything betwween parenthesis)
        if (! preg_match_all(static::PATTERN_CITATION, $text, $citationsMatch))
            return;

        $logContext = [
            'style' => static::NAME,
        ];

        foreach ($citationsMatch[1] as $group) {
            $orgCitation = $logContext['citation'] = @trim($group);

            if (array_key_exists($orgCitation, $replacements)) {
                $this->logger->debug('Reference already found', $logContext);
                continue;
            }

            // Sanetize the string removing spaces and rare characters to improve search
            $group = str_replace([' ', '–'], ['', '-'], $group);
            $citations = explode(',', $group);
            $n = count($citations);
            $found = false;

            // We use a fragment to store all nodes with the references and text
            $citationFragment = $doc->createDocumentFragment();

            for ($m=0; $m < $n ; $m++) { 
                $parts = explode('-', $citations[$m]);

                // Ignore the group if anything not numeric is found
                if (! is_numeric($parts[0]) || (isset($parts[1]) && ! is_numeric($parts[1]))) {
                    $this->logger->warning('The reference is not numeric', $logContext);
                    continue 2;
                }

                // The ranges need to be expanded
                $i = (int) $parts[0];
                $j = (int) ($parts[1] ?? $i);
                for (; $i <= $j; $i++) {
                    $logContext['id'] = $i;
                    if ($repository->getById($i)) {
                        if (static::DEBUG) $this->logger->debug('Reference found by search criteria', $logContext);
                        $citationFragment->appendChild($this->createRefNode($doc, $i, $i));
                        $found = true;
                    } else {
                        if (static::DEBUG) $this->logger->debug('Reference not found', $logContext);
                        $citationFragment->appendChild($doc->createTextNode($i));
                    }

                    // Add the separator
                    if ($i < $j) {
                        $citationFragment->appendChild($doc->createTextNode(','));
                    }
                }

                // Add the separator
                if ($m + 1 < $n) {
                    $citationFragment->appendChild($doc->createTextNode(','));
                }
            }

            // Ignore fragments without references
            if (! $found) {
                continue;
            }

            $citationFragment->insertBefore($doc->createTextNode('('), $citationFragment->firstChild);
            $citationFragment->appendChild($doc->createTextNode(')'));
            $replacements['('.$orgCitation.')'] = $citationFragment;
        }
    }
}
