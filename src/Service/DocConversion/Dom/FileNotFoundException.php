<?php

namespace App\Service\DocConversion\Dom;

use Exception;

class FileNotFoundException extends Exception
{
    public function __construct(string $file, $code = 0, \Throwable $previous = null)
    {
        parent::__construct('File not found: '.$file, $code, $previous);
    }
}
