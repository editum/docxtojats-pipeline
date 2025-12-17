<?php

namespace App\Service\DocConversion\Dom;

use Exception;

class FileNotReadableException extends Exception
{
    public function __construct(string $file, $code = 0, \Throwable $previous = null)
    {
        parent::__construct('File not readable: '.$file, $code, $previous);
    }
}
