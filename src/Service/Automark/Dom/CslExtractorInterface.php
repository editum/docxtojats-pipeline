<?php

namespace App\Service\Automark\Dom;

interface CslExtractorInterface
{
    const OUTPUT_CSL    = 'csl';
    const OUTPUT_BIB    = 'bib';
    const OUTPUT_JSON   = 'json';
    const OUTPUT_REF    = 'ref';

    /**
     * Executes the extraction process using the configured binary.
     *
     * @param string    $input              Path to the input file
     * @param ?string   $output             Optional path for the output file; if null, returns stdout
     * @param string    ...$outputFormats   One or more output formats to generate
     *
     * @return string|bool|null Returns a string if no $output is specified, or true/false if $output is provided
     */
    public function __invoke(string $input, ?string $output, string ...$outputFormats);
}
