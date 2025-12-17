<?php

namespace App\Service\Automark\Dom\Caption;

final class TablesCaptionSetter extends AbstractCaptionSetter
{
    const REF_TYPE = 'table';
    const QUERY_ELEMENT = '/article/body//table-wrap';
}
