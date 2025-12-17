<?php

namespace App\Service\Automark\Dom;

use DOMDocument;
use DOMDocumentType;

final class JatsFlavourDetector
{
    const JATS      = 'jats';
    const SCIELO    = 'scielo';

    /**
     * Determines if a DOMDocument is a JATS or a flavour like SciELO (SPS).
     *
     * @param \DOMDocument $doc
     * @param ?string $fallback the value to return if the type can't be determined
     * @return ?string 'jats'|'scielo'|$fallback
     */
    function __invoke(DOMDocument $doc, ?string $fallback = null): ?string
    {
        if (! $doc->documentElement || $doc->documentElement->tagName !== 'article') {
            return $fallback;
        }

        // Extract doctype info
        $publicId = null;
        $systemId = null;
        if ($doc->doctype instanceof DOMDocumentType) {
            $publicId = $doc->doctype->publicId;
            $systemId = $doc->doctype->systemId;
        }

        // Extract article atributes
        $article = $doc->documentElement;
        $namespaceURI = $article->namespaceURI;
        $specificUse = $article->hasAttribute('specific-use') ? $article->getAttribute('specific-use') : null;

        // SciELO
        if (
            ($publicId && preg_match('/\b(?:scielo|sps)\b/i', $publicId)) ||
            ($systemId && preg_match('/\b(?:scielo|sps)\b/i', $systemId)) ||
            ($specificUse && preg_match('/^sps-\d+(\.\d+)?$/i', $specificUse))
        ){
            return self::SCIELO;
        }

        // JATS
        if (
            ($publicId && preg_match('/(?:\b(?:jats|nlm)\b)/i', $publicId)) ||
            ($systemId && preg_match('/(?:\b(?:jats|nlm)\b)/i', $systemId)) ||
            ($namespaceURI && preg_match('/(?:jats\.nlm\.nih\.gov)?|ncbi\.nlm\.nih\.gov)/i', $namespaceURI))
        ) {
            return self::JATS;
        }

        return $fallback;
    }
}
