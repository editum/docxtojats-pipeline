<?php

namespace App\Service\Automark\Dom\Caption;

use App\Component\Xml\XmlUtils;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

abstract class AbstractCaptionSetter
{
    const REF_TYPE = '';
    const QUERY_ELEMENT = '';

    const DEFAULT_NOTE_KEYWORDS = [
        'nota',
        'notas',
        'note',
        'notes',
        'subtítulo',
        'subtitulo',
        'subtitle',
        'subtitles',
        'sous-titre',
        'anmerkung',
        'anmerkungen',
        'hinweis',
        'hinweise',
        'untertitel',
    ];

    static ?bool $titlePrecedesElement = null;

    protected string $elQuery;
    protected string $captionRegex;
    protected string $noteRegex;
    protected array $keywords;
    protected array $noteKeywords;

    /**
     * @param array<string> $keywords Keywords used to identify plausible category titles.
     * @param array<string> $noteKeywords Keywords used to identify plausible note/subtitle paragraphs.
     */
    public function __construct(array $keywords, array $noteKeywords = [])
    {
        $this->elQuery = static::QUERY_ELEMENT;
        $this->keywords = $keywords;
        $this->captionRegex = $this->getCaptionRegex();

        $mergedNoteKeywords = array_unique(array_merge(
            self::DEFAULT_NOTE_KEYWORDS,
            array_map('mb_strtolower', $noteKeywords)
        ));
        $this->noteKeywords = array_values($mergedNoteKeywords);
        $this->noteRegex = $this->getNoteRegex();
    }

    /**
     * Reset the global title position.
     */
    public static function resetTitlePosition(): void
    {
        self::$titlePrecedesElement = null;
    }

    /**
     * It will try to find the title before and after the element.
     * Upon determination it will use the same position for consecutive objects.
     * Returns an array of label and node references to each element for replacements.
     * 
     * @param DOMXPath $xpath
     * @param bool $setTitleReference sets a reference to the image where the title is
     * @return array array of replacements ['label' => DomNode]
     */
    public function __invoke(DOMXPath $xpath, bool $setTitleReference): array
    {
        $replacements = [];

        if (! $elements = $xpath->query($this->elQuery))
            return [];

        foreach ($elements as $el) {
            if ($labelEl = $xpath->query('./label', $el)->item(0)) {
                $this->elementWithCaptionSetter($el, $labelEl, $xpath, $setTitleReference, $replacements);
            } else {
                $this->elementSetter($el, $xpath, $setTitleReference, $replacements);
            }
        }

        return $replacements;
    }

    /**
     * Regular case where the elements have no label.
     */
    protected function elementSetter(
        DOMElement $el,
        DOMXPath $xpath,
        bool $setTitleReference,
        array &$replacements
    ): void {
        $doc = $el->ownerDocument;
        $rid = $el->getAttribute('id');

        $captionParts = null;
        $captionParagraphEl = null;

        $prev1 = $this->getPreviousSiblingElement($el);
        $prev2 = $prev1 ? $this->getPreviousSiblingElement($prev1) : null;
        $next1 = $this->getNextSiblingElement($el);
        $next2 = $next1 ? $this->getNextSiblingElement($next1) : null;

        // Find out if the caption comes in previous or next paragraph
        if (null === self::$titlePrecedesElement) {
            if ($captionParts = $this->extractCaption($prev1)) {
                $captionParagraphEl = $prev1;
                self::$titlePrecedesElement = true;
            } elseif ($this->isNoteParagraph($prev1) && ($captionParts = $this->extractCaption($prev2))) {
                $captionParagraphEl = $prev2;
                self::$titlePrecedesElement = true;
            } elseif ($captionParts = $this->extractCaption($next1)) {
                $captionParagraphEl = $next1;
                self::$titlePrecedesElement = false;
            } elseif ($this->isNoteParagraph($next1) && ($captionParts = $this->extractCaption($next2))) {
                $captionParagraphEl = $next2;
                self::$titlePrecedesElement = false;
            }
        } elseif (self::$titlePrecedesElement) {
            if ($captionParts = $this->extractCaption($prev1)) {
                $captionParagraphEl = $prev1;
            } elseif ($this->isNoteParagraph($prev1) && ($captionParts = $this->extractCaption($prev2))) {
                $captionParagraphEl = $prev2;
            } elseif ($captionParts = $this->extractCaption($next1)) {
                $captionParagraphEl = $next1;
            } elseif ($this->isNoteParagraph($next1) && ($captionParts = $this->extractCaption($next2))) {
                $captionParagraphEl = $next2;
            }
        } else {
            if ($captionParts = $this->extractCaption($next1)) {
                $captionParagraphEl = $next1;
            } elseif ($this->isNoteParagraph($next1) && ($captionParts = $this->extractCaption($next2))) {
                $captionParagraphEl = $next2;
            } elseif ($captionParts = $this->extractCaption($prev1)) {
                $captionParagraphEl = $prev1;
            } elseif ($this->isNoteParagraph($prev1) && ($captionParts = $this->extractCaption($prev2))) {
                $captionParagraphEl = $prev2;
            }
        }

        if ($captionParts && $captionParagraphEl) {
            list($category, $id, $title, $description) = $captionParts;

            // Format caption parts
            $label = $this->formatLabel($category, $id);
            $title = $this->formatTitle($title);
            $description = $this->formatDescription($description);

            // Set the caption nodes
            $this->setCaption($xpath, $el, $label, $title, $description);

            // Add the replacement for the node
            $xrefNode = $this->createRefNode($doc, $rid, $label);
            $replacements[$label] = $xrefNode;

            // Remove or replace with reference the caption paragraph
            if ($setTitleReference) {
                $xrefNode = clone $xrefNode;
                $xrefNode->nodeValue = $captionParagraphEl->textContent;
                $captionParagraphEl->textContent = '';
                $captionParagraphEl->appendChild($xrefNode);
            } else {
                $captionParagraphEl->parentNode->removeChild($captionParagraphEl);
            }
        }

        // Process any note paragraphs adjacent to the element
        $this->processNotes($xpath, $el);
    }

    /**
     * Special case when the figures have already a label.
     */
    protected function elementWithCaptionSetter(
        DOMElement $el,
        DOMElement $labelEl,
        DOMXPath $xpath,
        bool $setTitleReference,
        array &$replacements
    ): void {
        $doc = $el->ownerDocument;
        $rid = $el->getAttribute('id');

        $label = $labelEl->textContent;
        $title = null;

        if ($titleEl = $xpath->query('./caption/title', $el)->item(0)) {
            $title = $titleEl->textContent;
        } elseif ($captionParts = $this->extractCaption($labelEl)) {
            // Normalize the label when there is no title

            list($category, $id, $title, $description) = $captionParts;
            $label = $this->formatLabel($category, $id);
            $title = $this->formatTitle($title);
            $description = $this->formatDescription($description);
            $this->setCaption($xpath, $el, $label, $title, $description);
        }

        // Replacements for the node
        $xrefNode = $this->createRefNode($doc, $rid, $label);
        $replacements[$label] = $xrefNode;

        // Add a reference before or after the image
        if ($setTitleReference) {
            $content = $label;
            if ($title) {
                $content .= ': '.$title;
            }
            $xrefNode = clone $xrefNode;
            $xrefNode->nodeValue = $content;
            if (null === self::$titlePrecedesElement) {
                self::$titlePrecedesElement = false;
            }
            $paragraph = $doc->createElement('p');
            $paragraph->appendChild($xrefNode);
            if (self::$titlePrecedesElement) {
                $el->parentNode->insertBefore($paragraph, $el);
            } else {
                $el->parentNode->insertBefore($paragraph, $el->nextSibling);
            }
        }

        // Process any note paragraphs adjacent to the element
        $this->processNotes($xpath, $el);
    }

    /**
     * Attaches a note paragraph to the target element (table or figure).
     */
    abstract protected function setNote(DOMXPath $xpath, DOMElement $el, DOMElement $noteParagraphEl): void;

    /**
     * Processes any note paragraphs (preceding or following) and attaches them to the element.
     * Skips reference paragraphs pointing to this element.
     */
    protected function processNotes(DOMXPath $xpath, DOMElement $el): void
    {
        $rid = $el->getAttribute('id');

        // 1. Process notes in following sibling paragraphs (most common: notes placed after table/figure)
        while (($next = $this->getNextSiblingElement($el, $rid)) && $this->isNoteParagraph($next)) {
            $this->setNote($xpath, $el, $next);
        }

        // 2. Process notes in preceding sibling paragraphs (notes/subtitles placed before table/figure)
        while (($prev = $this->getPreviousSiblingElement($el, $rid)) && $this->isNoteParagraph($prev)) {
            $this->setNote($xpath, $el, $prev);
        }
    }

    /**
     * Checks if a paragraph is a generated reference paragraph containing an xref to this element.
     */
    protected function isReferenceParagraph(?DOMNode $node, string $rid): bool
    {
        if (null === $node || $node->nodeType !== XML_ELEMENT_NODE || $node->nodeName !== 'p') {
            return false;
        }

        $xrefs = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) === '') {
                continue;
            }
            if ($child->nodeType === XML_ELEMENT_NODE && $child->nodeName === 'xref') {
                $xrefs[] = $child;
                continue;
            }
            return false;
        }

        return count($xrefs) === 1 && $xrefs[0]->getAttribute('rid') === $rid;
    }

    /**
     * Gets the previous sibling element, skipping whitespace text nodes, empty paragraphs,
     * and optionally a reference paragraph pointing to $skipRid.
     */
    protected function getPreviousSiblingElement(?DOMNode $node, ?string $skipRid = null): ?DOMNode
    {
        $curr = $node ? $node->previousSibling : null;
        while ($curr) {
            if ($curr->nodeType === XML_TEXT_NODE && trim($curr->textContent) === '') {
                $curr = $curr->previousSibling;
                continue;
            }
            if ($curr->nodeType === XML_ELEMENT_NODE && $curr->nodeName === 'p' && trim($curr->textContent) === '') {
                $curr = $curr->previousSibling;
                continue;
            }
            if ($skipRid && $this->isReferenceParagraph($curr, $skipRid)) {
                $curr = $curr->previousSibling;
                continue;
            }
            break;
        }
        return ($curr && $curr->nodeType === XML_ELEMENT_NODE) ? $curr : null;
    }

    /**
     * Gets the next sibling element, skipping whitespace text nodes, empty paragraphs,
     * and optionally a reference paragraph pointing to $skipRid.
     */
    protected function getNextSiblingElement(?DOMNode $node, ?string $skipRid = null): ?DOMNode
    {
        $curr = $node ? $node->nextSibling : null;
        while ($curr) {
            if ($curr->nodeType === XML_TEXT_NODE && trim($curr->textContent) === '') {
                $curr = $curr->nextSibling;
                continue;
            }
            if ($curr->nodeType === XML_ELEMENT_NODE && $curr->nodeName === 'p' && trim($curr->textContent) === '') {
                $curr = $curr->nextSibling;
                continue;
            }
            if ($skipRid && $this->isReferenceParagraph($curr, $skipRid)) {
                $curr = $curr->nextSibling;
                continue;
            }
            break;
        }
        return ($curr && $curr->nodeType === XML_ELEMENT_NODE) ? $curr : null;
    }

    /**
     * Regex used to find a valid note or subtitle paragraph.
     */
    protected function getNoteRegex(): string
    {
        $escaped = array_map(function ($kw) {
            return preg_quote($kw, '/');
        }, $this->noteKeywords);

        return '/^(' . implode('|', $escaped) . ')(\s*[:.,;—–-]|\s+(?:\d+|[a-zA-Z*]{1,3})[:.,;—–-]|\s+|$)/iu';
    }

    /**
     * Checks if a DOM node is a paragraph representing a note or subtitle.
     */
    protected function isNoteParagraph(?DOMNode $el): bool
    {
        if (null !== $el
            && $el->nodeType === XML_ELEMENT_NODE
            && $el->nodeName === 'p'
        ) {
            $text = preg_replace('/^\s+/u', '', $el->textContent);
            return (bool) preg_match($this->noteRegex, $text);
        }
        return false;
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
        $xrefNode = $doc->createElement('xref', $label);
        $xrefNode->setAttribute('ref-type', static::REF_TYPE);
        $xrefNode->setAttribute('rid', $rid);
        return $xrefNode;
    }

    /**
     * Formats the label. Example "Figure 1"
     */
    protected function formatLabel(string $category, string $id): string
    {
        return sprintf('%s %s', ucfirst($category), $id);
    }

    /**
     * Formats the title. "Mi figure"
     */
    protected function formatTitle(string $title): string
    {
        return ucfirst($title);
    }

    protected function formatDescription(?string $description): ?string
    {
        return $description;
    }

    /**
     * Regex used to find a valid title. Example Label id: text
     */
    protected function getCaptionRegex(): string
    {
        return '/^('.implode('|', $this->keywords).')\s+(\w+|\d+)[:.,;]\s*([^.]+)(?:\.\s*)?(.*)?$/i';
        //return '/^('.implode('|', static::LABEL_CATEGORIES).')\s+(\w+|\d+)[:.,;]\s*([^\.]+)\.\s*(.*)?$/i';
        //return '/^('.implode('|', static::LABEL_CATEGORIES).')\s(\w+|\d+)[:.,;](.+)/i';
    }

    /**
     * Extracts the title parts that match the regex expression from the
     * textContent of an element.
     *
     * @param ?DOMNode $el
     * @return null|array ['label', 'id', 'title', 'description' ], null if regex doesn't match
     */
    protected function extractCaption(?DOMNode $el): ?array
    {
        if (null != $el 
            && ($el->nodeName === 'p' || $el->nodeName === 'label')
            && preg_match($this->captionRegex, $el->textContent, $parts)
        ) {
            return [ $parts[1], $parts[2], trim($parts[3]), trim($parts[4] ?? '') ];
        }
        return null;
    }

    /**
     * Sets the element label, title and caption.
     * The caption is modified only when is empty.
     *
     * @param DOMXPath $xpath
     * @param DOMNode $el the context node
     * @param string $label
     * @param ?string $title
     */
    protected function setCaption(DOMXPath $xpath, DOMNode $el, string $label, ?string $title, ?string $description): void
    {
        // Write the caption with the title
        if ($title || $description) {
            if (! $captionEl = $xpath->query('./caption', $el)->item(0)) {
                $captionEl = XmlUtils::prependNewElementTo($el, 'caption');
                if (! empty($description))
                    XmlUtils::appendNewElementTo($captionEl, 'p', $description);
            }
        }

        if ($title) {
            if (! $titleEl = $xpath->query('./title', $captionEl)->item(0))
                $titleEl = XmlUtils::prependNewElementTo($captionEl, 'title', $title);
            else
                $titleEl->nodeValue = $title;
        }

        // Write the label
        if (! $labelEl = $xpath->query('./label', $el)->item(0))
            XmlUtils::prependNewElementTo($el, 'label', $label);
        else
            $labelEl->nodeValue = $label;
    }
}
