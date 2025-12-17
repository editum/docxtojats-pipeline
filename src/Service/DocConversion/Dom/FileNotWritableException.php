<?php

namespace App\Service\DocConversion\Dom;

use Exception;

class FileNotWritableException extends Exception
{
    public function __construct(string $file, $code = 0, \Throwable $previous = null)
    {
        parent::__construct('File not writable: '.$file, $code, $previous);
    }
}
