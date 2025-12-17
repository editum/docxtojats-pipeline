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

    static ?bool $titlePrecedesElement = null;

    protected string $elQuery;
    protected string $captionRegex;
    protected array $keywords;

    /**
     * @param array<string> $keywords Keywords used to identify plausible category titles.
     */
    public function __construct(array $keywords)
    {
        $this->elQuery = static::QUERY_ELEMENT;
        $this->keywords = $keywords;
        $this->captionRegex = $this->getCaptionRegex();
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
     * @param bool $setTittleReference sets a reference to the image where the title is
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

        // Find out if the caption comes in previous or next paragraph
        if (null === self::$titlePrecedesElement) {
            if ($captionParts = $this->extractCaption($el->previousSibling)) {
                $captionParagraphEl = $el->previousSibling;
                self::$titlePrecedesElement = true;
            } elseif ($captionParts = $this->extractCaption($el->nextSibling)) {
                $captionParagraphEl = $el->nextSibling;
                self::$titlePrecedesElement = false;
            }
        } elseif ($captionParagraphEl = self::$titlePrecedesElement ? $el->previousSibling : $el->nextSibling) {
            $captionParts = $this->extractCaption($captionParagraphEl);
        }

        // Nothing to do
        if (! $captionParts) {
            return;
        }

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
            return [ $parts[1], $parts[2], trim($parts[3]), trim($parts[4]) ];
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
