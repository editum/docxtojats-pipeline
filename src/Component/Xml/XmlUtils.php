<?php

namespace App\Component\Xml;

use InvalidArgumentException;
use RuntimeException;

class XmlUtils
{
    /**
     * Creates and append a new element to another.
     * @param \DOMElement $parentEl the parent node
     * @param string $tagName with the localName of the new element
     * @param ?string $value with the value of the element, by default ''
     * @param ?array $attributes with the attributes to set, by default empty
     * @return \DOMElement with the newly generated element
     */
    public static function appendNewElementTo(
        \DOMElement $parentEl,
        string $tagName,
        ?string $value = '',
        ?array $attributes = []
    ): \DOMElement {
        $doc = $parentEl->ownerDocument;
        $el = $doc->createElement($tagName);
        if ($value !== null && $value !== '') {
            $el->appendChild($doc->createTextNode($value));
        }
        foreach ($attributes as $attr => $val) {
            $el->setAttribute($attr, $val);
        }
        $parentEl->appendChild($el);
        return $el;
    }

    /**
     * Creates and prepends a new element to another.
     * @param \DOMElement $parentEl the parent node
     * @param string $tagName with the localName of the new element
     * @param ?string $value with the value of the element, by default ''
     * @param ?array $attributes with the attributes to set, by default empty
     * @return \DOMElement with the newly generated element
     */
    public static function prependNewElementTo(
        \DOMElement $parentEl,
        string $tagName,
        ?string $value = '',
        ?array $attributes = []
    ): \DOMElement {
        $doc = $parentEl->ownerDocument;
        $el = $doc->createElement($tagName);
        if ($value !== null && $value !== '') {
            $el->appendChild($doc->createTextNode($value));
        }
        foreach ($attributes as $attr => $val) {
            $el->setAttribute($attr, $val);
        }
        $parentEl->insertBefore($el, $parentEl->firstChild);
        return $el;
    }

    /**
     * Given a list of "labels" and their nodes, search and replace all the
     * labels in the text with the nodes.
     * 
     * To get the best results it's recomended to normalize the dicument 
     * $doc->normalize().
     *
     * @param \DOMDocument  $doc
     * @param string        $query              XPath to select the nodes to process
     * @param array         $replacements       ['label1' => DOMNode1, ...]
     * @param bool          $wordBoundary       Require word boundaries around the labels
     */
    public static function replaceTextWithNodes(
        \DOMDocument $doc,
        string $query,
        array $replacements,
        bool $wordBoundary = true
    ): void {
        self::_replaceTextWithNodes($doc, $query, $replacements, false, $wordBoundary);
    }

    /**
     * Given a list of "labels" and their nodes, search and replace all the
     * labels in the text with the nodes.
     *
     * Case insensitive version.
     *
     * To get the best results it's recomended to normalize the dicument 
     * $doc->normalize().
     *
     * @param \DOMDocument  $doc
     * @param string        $query              XPath to select the nodes to process
     * @param array         $replacements       ['label1' => DOMNode1, ...]
     * @param bool          $wordBoundary       Require word boundaries around the labels
     */
    public static function iReplaceTextWithNodes(
        \DOMDocument $doc,
        string $query,
        array $replacements,
        bool $wordBoundary = true
    ): void {
        self::_replaceTextWithNodes($doc, $query, $replacements, true, $wordBoundary);
    }

    /**
     * Given a list of "labels" and their nodes, search and replace all the
     * labels in the text with the nodes.
     *
     * @param \DOMDocument  $doc
     * @param string        $query              XPath to select the nodes to process
     * @param array         $replacements       ['label1' => DOMNode1, ...]
     * @param bool          $caseInsensitive    Ignore case
     * @param bool          $wordBoundary       Require word boundaries around the labels
     */
    private static function _replaceTextWithNodes(
        \DOMDocument $doc,
        string $query,
        array $replacements,
        bool $caseInsensitive = false,
        bool $wordBoundary = true
    ): void {

        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query($query);

        // Sort labels by desscending lenght to prevent prefix colissions
        uksort($replacements, function ($a, $b) {
            return mb_strlen($b) <=> mb_strlen($a);
        });

        // Define strpos function based in case sensitive
        $f_strpos = $caseInsensitive ? 'mb_stripos' : 'mb_strpos';

        foreach ($nodes as $node) {
            /** @var \DOMNode $node */
            $parent = $node->parentNode;
            if (! $parent instanceof \DOMNode) {
                continue;
            }

            /** @var array with the list of segments where to search */
            $segments = [$node->nodeValue];
            $hasNewSegments = false;

            foreach ($replacements as $label => $nodeTemplate) {
                // Test parameters
                if (! is_string($label)) {
                    throw new InvalidArgumentException("The label \"{$label}\" for replacements must be a string");
                }
                if (! $nodeTemplate instanceof \DOMNode && ! $nodeTemplate instanceof \DOMDocumentFragment) {
                    throw new InvalidArgumentException("The replacement for \"{$label}\" must be a DOMNode");
                }

                $labelLen = mb_strlen($label);
                /** @var array new segments created after replacing the string */
                $newSegments = [];

                // Search and create the new segments with the replacements
                foreach ($segments as $segment) {
                    if (is_string($segment)) {
                        // Text segment
                        $offset = 0;

                        // Label matches
                        $found = false;
                        while (($pos = $f_strpos($segment, $label, $offset)) !== false) {

                            // Check word boundary
                            if ($wordBoundary) {
                                $before = ($pos === 0) ? '' : mb_substr($segment, $pos - 1, 1);
                                $after = mb_substr($segment, $pos + $labelLen, 1);

                                $isBeforeBoundary = $before === '' || !preg_match('/[\pL\pN]/u', $before);
                                $isAfterBoundary = $after === '' | !preg_match('/[\pL\pN]/u', $after);

                                if (!$isBeforeBoundary || !$isAfterBoundary) {
                                    $offset = $pos + 1;
                                    continue;
                                }
                            }

                            // Add text before match
                            if ($pos > $offset) {
                                $newSegments[] = mb_substr($segment, $offset, $pos - $offset);
                            }

                            // Import replacement node
                            if (! $newNode = $doc->importNode(clone $nodeTemplate, true)) {
                                throw new RuntimeException("Copying the replacement node for \"{$label}\"");
                            }

                            // Change capitalization of the new node whe the text is different when the operation is case insensitive
                            if ($caseInsensitive && ! $newNode instanceof \DOMDocumentFragment) {
                                $matchedText = mb_substr($segment, $pos, $labelLen);
                                if ($newNode->nodeValue !== $matchedText && mb_strtolower($newNode->nodeValue) === mb_strtolower($matchedText)) {
                                    $newNode->nodeValue = $matchedText;
                                }
                            }

                            $newSegments[] = $newNode;
                            $offset = $pos + $labelLen;
                            $found = true;
                            $hasNewSegments = true;
                        }

                        if (! $found) {
                            // Readd the segment
                            $newSegments[] = $segment;
                        } elseif ($offset < mb_strlen($segment)) {
                            // Remaining text
                            $newSegments[] = mb_substr($segment, $offset);
                        }
                    } else {
                        // This segment is already a node, we must keep it
                        $newSegments[] = $segment;
                    }
                }
                $segments = $newSegments;
            }

            // Replace the original node with the new segments
            if (! $hasNewSegments) {
                continue;
            }

            foreach ($segments as $segment) {
                if ($segment instanceof \DOMNode) {
                    $parent->insertBefore($segment, $node);
                } else {
                    $parent->insertBefore($doc->createTextNode($segment), $node);
                }
            }

            $parent->removeChild($node);
        }
    }
}
