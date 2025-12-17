<?php

namespace App\Service\DocConversion\Dom\ExternalCommand;

interface ExifToolInterface
{
    /**
     * @param string $file
     * @param string $options exiftool options
     * @return bool
     * @throws InvalidArgumentException when the arguments are invalid
     * @throws RuntimeException when the conversion fails
     */
    public function __invoke(string $file, string ...$options): bool;
}
