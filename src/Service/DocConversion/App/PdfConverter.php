<?php

namespace App\Service\DocConversion\App;

use App\Service\DocConversion\Dom\DocConversionHandler;
use App\Service\DocConversion\Dom\ExternalCommand\LibreOfficeInterface;

final class PdfConverter
{
    private DocConversionHandler $conversionHandler;
    private LibreOfficeInterface $libreOffice;
    
    public function __construct(
        DocConversionHandler $conversionHandler,
        LibreOfficeInterface $libreOffice
    ){
        $this->conversionHandler = $conversionHandler;
        $this->libreOffice = $libreOffice;
    }

    /**
     * Converts a file to pdf.
     * 
     * @param string $input
     * @param string $output
     * @param bool $overwrite true to overwrte file if exists
     * @return bool true if success
     * @throws FileNotFoundException
     * @throws InvalidArgumentException
     * @throws InvalidArgumentException
     */
    public function __invoke(string $input, string $output, bool $overwrite = false): bool
    {
        return $this->conversionHandler->convert($input, $output, $overwrite, function($input, $output): bool {
            return ($this->libreOffice)($input, $output, 'pdf');
        });
    }
}
