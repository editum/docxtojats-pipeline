<?php

namespace App\Service\DocConversion\App;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use RuntimeException;
use Twig\Error\RuntimeError;
use ZipArchive;

class JatsZipArchiver
{
    const ZIP_PREFIX = 'jatsz_';

    /**
     * Archives a JATS XML file with all its images.
     * The file is created in a temporal folder and must be deleted.
     *
     * @param string $inputFile Path al fichero JATS XML
     * @return string Binario del zip (para enviar como response o guardar)
     * @throws InvalidArgumentException
     * @throws RuntimeError
     */
    public function __invoke(string $inputFile): string
    {
        if (!is_file($inputFile) || !is_readable($inputFile)) {
            throw new InvalidArgumentException("JATS file not readable: {$inputFile}");
        }

        $dom = new DOMDocument();
        $dom->load($inputFile);
        $xpath = new DOMXPath($dom);

        // Create temporal zip, must have the zip extension
        if ($tmpZipFile = tempnam(sys_get_temp_dir(), self::ZIP_PREFIX)) {
            rename($tmpZipFile, $tmpZipFile.'.zip');
            $tmpZipFile .= '.zip';
        } else {
            throw new RuntimeException('Unable to create temporal ZIP file', 1);
        }

        $zip = new ZipArchive();
        $zip->open($tmpZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Add the jats file to  the zip
        $zip->addFile($inputFile, basename($inputFile));

        // Add images to zip file
        $baseDir = dirname($inputFile);

        $xpath->registerNamespace('xlink', 'http://www.w3.org/1999/xlink');

        /** @var DOMElement $node */
        foreach ($xpath->query('//graphic[@xlink:href]') as $node) {
            $href = $node->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
            if (!$href) {
                continue;
            }
            $imgPath = realpath($baseDir . DIRECTORY_SEPARATOR . $href);
            if ($imgPath && is_file($imgPath)) {
                $zip->addFile($imgPath, $href);
            }
        }

        $zip->close();

        return $tmpZipFile;
    }
}
