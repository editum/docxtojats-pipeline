<?php

namespace App\Service\DocConversion\App;

use App\Service\DocConversion\Dom\ExternalCommand\ExifToolInterface;

final class Anonymizer
{
    private ExifToolInterface $exiftool;

    public function __construct(ExifToolInterface $exiftool)
    {
        $this->exiftool = $exiftool;
    }

    public function __invoke(string $file): bool
    {
        return ($this->exiftool)($file, '-all:all=','-overwrite_original');
    }
}
