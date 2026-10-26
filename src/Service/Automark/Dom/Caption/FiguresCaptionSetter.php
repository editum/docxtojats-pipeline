<?php

namespace App\Service\Automark\Dom\Caption;

use App\Component\Xml\XmlUtils;
use DOMElement;
use DOMXPath;

final class FiguresCaptionSetter extends AbstractCaptionSetter
{
    const REF_TYPE = 'fig';
    const QUERY_ELEMENT = '/article/body//fig';

    protected function setNote(DOMXPath $xpath, DOMElement $el, DOMElement $noteParagraphEl): void
    {
        $captionEl = $xpath->query('./caption', $el)->item(0);
        if (!$captionEl) {
            $captionEl = XmlUtils::prependNewElementTo($el, 'caption');
        }
        $captionEl->appendChild($noteParagraphEl);
    }
}
