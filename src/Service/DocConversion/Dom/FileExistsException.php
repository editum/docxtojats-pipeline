<?php

namespace App\Service\DocConversion\Dom;

use Exception;

class FileExistsException extends Exception
{
    public function __construct(string $file, $code = 0, \Throwable $previous = null)
    {
        parent::__construct('File already exists: '.$file, $code, $previous);
    }
}
