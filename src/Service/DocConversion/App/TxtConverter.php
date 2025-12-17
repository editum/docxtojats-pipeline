<?php

namespace App\Service\DocConversion\App;

use App\Service\DocConversion\Dom\DocConversionHandler;
use App\Service\DocConversion\Dom\ExternalCommand\PandocInterface;
use Mimey\MimeTypes;

final class TxtConverter
{
    private DocConversionHandler $conversionHandler;
    private PandocInterface $pandoc;
    private MimeTypes $mimeTypes;
    
    public function __construct(
        DocConversionHandler $conversionHandler,
        PandocInterface $pandoc
    ){
        $this->conversionHandler = $conversionHandler;
        $this->pandoc = $pandoc;
        $this->mimeTypes = new MimeTypes();
    }

    /**
     * Converts a file to txt.
     * 
     * @param string $input
     * @param string $output
     * @param bool $overwrite true to overwrite files
     * @param ?string $from force mimetype (extension)
     * @return bool true if success
     * @throws FileNotFoundException
     * @throws InvalidArgumentException
     * @throws InvalidArgumentException
     */
    public function __invoke(string $input, string $output, bool $overwrite = false, ?string $from = null): bool
    {
        $from = $from ? mb_strtolower($from) : $this->mimeTypes->getExtension(mime_content_type($input));
        return $this->conversionHandler->convert($input, $output, $overwrite, function($input, $output) use ($from): bool {
            return ($this->pandoc)($input, $from, $output, 'plain');
        });
    }
}
