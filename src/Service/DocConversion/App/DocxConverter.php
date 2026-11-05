<?php

namespace App\Service\DocConversion\App;

use App\Service\DocConversion\Dom\DocConversionHandler;
use App\Service\DocConversion\Dom\ExternalCommand\LibreOfficeInterface;
use DOMDocument;
use DOMXPath;
use ZipArchive;

final class DocxConverter
{
    private const OMML_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/math';

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
     * Converts a file to docx.
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
            $success = ($this->libreOffice)($input, $output, 'docx');
            if ($success) {
                $this->preserveOmmlFormulas($input, $output);
            }
            return $success;
        });
    }

    /**
     * Restores original OMML (<m:oMath>) nodes when normalizing a DOCX file with LibreOffice,
     * preventing LibreOffice's internal StarMath filter from truncating or altering equations.
     */
    private function preserveOmmlFormulas(string $sourceFile, string $targetFile): void
    {
        $sourceZip = new ZipArchive();
        if ($sourceZip->open($sourceFile) !== true) {
            return;
        }
        $sourceXml = $sourceZip->getFromName('word/document.xml');
        $sourceZip->close();

        if (!is_string($sourceXml) || strpos($sourceXml, 'oMath') === false) {
            return;
        }

        $sourceDom = new DOMDocument();
        if (!@$sourceDom->loadXML($sourceXml)) {
            return;
        }
        $sourceXpath = new DOMXPath($sourceDom);
        $sourceXpath->registerNamespace('m', self::OMML_NS);
        $sourceNodes = $sourceXpath->query('//m:oMath[not(ancestor::m:oMath)]');
        if (!$sourceNodes || $sourceNodes->length === 0) {
            return;
        }

        $targetZip = new ZipArchive();
        if ($targetZip->open($targetFile) !== true) {
            return;
        }
        $targetXml = $targetZip->getFromName('word/document.xml');
        if (!is_string($targetXml)) {
            $targetZip->close();
            return;
        }

        $targetDom = new DOMDocument();
        if (!@$targetDom->loadXML($targetXml)) {
            $targetZip->close();
            return;
        }
        $targetXpath = new DOMXPath($targetDom);
        $targetXpath->registerNamespace('m', self::OMML_NS);
        $targetNodes = $targetXpath->query('//m:oMath[not(ancestor::m:oMath)]');

        if ($targetNodes && $targetNodes->length === $sourceNodes->length) {
            for ($i = 0; $i < $sourceNodes->length; $i++) {
                $sourceNode = $sourceNodes->item($i);
                $targetNode = $targetNodes->item($i);
                if ($sourceNode && $targetNode && $targetNode->parentNode) {
                    $imported = $targetDom->importNode($sourceNode, true);
                    $targetNode->parentNode->replaceChild($imported, $targetNode);
                }
            }
            $updatedXml = $targetDom->saveXML();
            if ($updatedXml !== false) {
                $targetZip->addFromString('word/document.xml', $updatedXml);
            }
        }

        $targetZip->close();
    }
}
