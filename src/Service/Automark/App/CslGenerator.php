<?php

namespace App\Service\Automark\App;

use App\Service\Automark\Dom\BibliographyTextExtractor;
use App\Service\Automark\Dom\CslExtractorInterface;
use App\Service\Automark\Dom\CslRepositoryInterface;
use DOMDocument;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use UnexpectedValueException;

final class CslGenerator implements LoggerAwareInterface
{
    private LoggerInterface $logger;
    private BibliographyTextExtractor $bibTextExtractor;
    private CslExtractorInterface $cslExtractor;

    public function __construct(
        BibliographyTextExtractor $bibTextExtractor,
        CslExtractorInterface $cslExtractor
    ){
        $this->logger = new NullLogger();
        $this->bibTextExtractor = $bibTextExtractor;
        $this->cslExtractor = $cslExtractor;
    }

    /**
     * Extracts the bibliography in Citation Style Language from a file and adds
     * the plain text references to the structure as the property "note".
     *
     * @param string|DOMDocument $input anyfile supported by the extractor
     * @return null|array CSL with added notes
     */
    public function __invoke($input): ?array
    {
        if (!is_string($input) && !$input instanceof DOMDocument) {
            throw new InvalidArgumentException('Error: '. __METHOD__.' $input must be a string or an instance of DOMDocument.');
        }

        // When a DOM is passed extract the bibliography section in a temporal .txt file as $input
        $doc = null;
        if ($input instanceof DOMDocument) {
            $this->logger->info('Generating CSL from XML JATS');
            $doc = $input;
            $text = ($this->bibTextExtractor)($doc);

            if (! $text) {
                return null;
            }

            // $input will point to the temporal file
            $tmpfile = tempnam(sys_get_temp_dir(), 'bib_');
            $input = $tmpfile.'.txt';
            if (! rename($tmpfile, $input) || false === file_put_contents($input, $text)) {
                unlink($input);
                throw new UnexpectedValueException('Error: '.__METHOD__.' could not create temporary file.');
            }

            // Ensure the file is cleaned since it was removed
            register_shutdown_function(function() use ($input) {
                if (file_exists($input)) {
                    unlink($input);
                }
            });

        } else {
            $this->logger->info('Generating CSL from '.'"'.$input.'"');
        }

        // Extract the bibliography and plain references
        $csl = null;
        $ref = null;
        if ($cslJson = ($this->cslExtractor)($input, null, CslExtractorInterface::OUTPUT_CSL)) {
            if ($refText = ($this->cslExtractor)($input, null, CslExtractorInterface::OUTPUT_REF)) {
                $csl = json_decode($cslJson);
                // BibliographyTextExtractor may introduce ' .' at the end of each line to help recognition
                $refText = preg_replace('/ \.$/m', '', $refText);
                $ref = explode("\n", trim($refText));
            }
        }
        // Insert each ref as "note" in the matching csl
        if (! empty($csl) && ! empty($ref) && count($csl) == count($ref)) {
            for ($i=0; $i < count($csl); $i++) {
                $csl[$i]->{CslRepositoryInterface::NOTE} = preg_replace('/\s+/', ' ', trim($ref[$i]));
            }
        } else {
            $csl = null;
        }

        // Delete temporal file when the input was a DOMDocument
        if ($doc) {
            unlink($input);
        }

        return $csl;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
