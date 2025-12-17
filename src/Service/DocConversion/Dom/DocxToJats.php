<?php

namespace App\Service\DocConversion\Dom;

use docx2jats\DOCXArchive;
use docx2jats\jats\Document as JatsDom;
use DOMNode;
use DOMXPath;
use ZipArchive;

/**
 * doc2jats wrapper
 */
class DocxToJats
{
    private string $input;

    public DOCXArchive $docxArchive;
    public JatsDom $jatsDom;
    public DOMXPath $jatsXpath;

    public function __construct(string $input)
    {
        // Supress malformed xml errors
        libxml_use_internal_errors(true);

        $this->input = $input;

        $errorReporting = error_reporting(E_ALL & ~E_NOTICE & ~E_USER_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        try {
            $this->docxArchive = new DOCXArchive($input);
            $this->jatsDom = new JatsDom($this->docxArchive);
        } catch (\Throwable $th) {
            throw $th;
        } finally {
            error_reporting($errorReporting);
        }
        $this->jatsXpath = new DOMXPath($this->jatsDom);
    }

        /**
     * @param string $output the zip file name
     * @param array $extraFiles extra files to write in the archive [{'filename':'data'},...]
     */
    public function archive(string $output, array $extraFiles = []): void
    {
        $zip = new ZipArchive();
        if (! $zip->open($output, ZipArchive::CREATE)) {
            throw new \Exception("Couldn't create archive ".$output, 1);
        }
        // Write XML
        $zip->addFromString(pathinfo($output)['filename'].'.xml', $this->jatsDom->saveXML());
        // Write media files
        foreach ($this->docxArchive->getMediaFilesContent() as $mediaFile => $data) {
            $zip->addFromString(pathinfo($mediaFile)['basename'], $data);
        }
        // Write extra files
        foreach ($extraFiles as $filename => $data) {
            $zip->addFromString($filename, $data);
        }
        $zip->close();
    }

    public function getContents(): string
    {
        return $this->jatsDom->saveXML();
    }

    public function getMediaFiles(): array
    {
        $files = [];
        foreach ($this->docxArchive->getMediaFilesContent() as $mediaFile => $data) {
            $files[pathinfo($mediaFile)['basename']] = $data;
        }
        return $files;
    }

    public function getFiles(string $outputname)
    {
        if (! $outputname) {
            $outputname = pathinfo($this->input)['filename'];
        }
        //$errorReporting = error_reporting(E_ERROR | E_PARSE);
        $errorReporting = error_reporting(E_ALL & ~E_NOTICE & ~E_USER_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        try {
            $files = [
                $outputname.'.xml' => $this->jatsDom->saveXML(),
            ];
            foreach ($this->docxArchive->getMediaFilesContent() as $mediaFile => $data) {
                $files[pathinfo($mediaFile)['basename']] = $data;
            }
        } catch (\Throwable $th) {
            throw $th;
        } finally {
            error_reporting($errorReporting);
        }
        return $files;
    }

    public function removeSectionsById(string ...$ids): void
    {
        foreach ($ids as $id) {
            // REVIEW For this to work we need the dtd and the document validated...
            // if ($node = $this->jatsDom->getElementById($id)) {
            //     $node->parentNode->removeChild($node);
            // }
            $nodes = $this->jatsXpath->query("//sec[@id='$id']");
            foreach ($nodes as $node) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    public function setFront(DOMNode $front): void
    {
        $newFront = $this->jatsDom->importNode($front, true);
        $oldFront = $this->jatsDom->getElementsByTagName('front')->item(0);
        $oldFront->parentNode->replaceChild($newFront, $oldFront);
    }
}
