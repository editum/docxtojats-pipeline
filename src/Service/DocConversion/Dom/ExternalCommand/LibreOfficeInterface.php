<?php

namespace App\Service\DocConversion\Dom\ExternalCommand;

interface LibreOfficeInterface
{
    /**
     * @param string $input input file
     * @param string $output output file
     * @param string $to file extension to convert to
     * @return bool
     * @throws InvalidArgumentException when the arguments are invalid
     * @throws RuntimeException when the conversion fails
     */
    public function __invoke(string $input, string $output, string $to): bool;
}
