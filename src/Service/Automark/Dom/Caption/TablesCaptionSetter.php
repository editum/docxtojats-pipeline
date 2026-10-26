<?php

namespace App\Service\Automark\Dom\Caption;

use DOMElement;
use DOMXPath;

final class TablesCaptionSetter extends AbstractCaptionSetter
{
    const REF_TYPE = 'table';
    const QUERY_ELEMENT = '/article/body//table-wrap';

    protected function setNote(DOMXPath $xpath, DOMElement $el, DOMElement $noteParagraphEl): void
    {
        $footEl = $xpath->query('./table-wrap-foot', $el)->item(0);
        if (!$footEl) {
            $footEl = $el->ownerDocument->createElement('table-wrap-foot');
            $el->appendChild($footEl);
        }
        $footEl->appendChild($noteParagraphEl);
    }
}
