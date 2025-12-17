<?php

namespace App\Service\DocConversion\Dom\ExternalCommand;

interface PandocInterface
{
    /**
     * @param string $input input file
     * @param string $output output file
     * @param string $to mimetype to convert to
     * @return bool
     * @throws InvalidArgumentException when the arguments are invalid
     * @throws RuntimeException when the conversion fails
     */
    public function __invoke(string $input, string $from, string $output, string $to): bool;
}
