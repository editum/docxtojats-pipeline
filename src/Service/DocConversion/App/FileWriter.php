<?php

namespace App\Service\DocConversion\App;

use ZipArchive;

class FileWriter
{
    /**
     * Writes files to a zip archive.
     * @param string $outputfile the zip file
     * @param array $files asociative array where the key is the name and the value the data to write.
     */
    public function archive(string $outputfile, array $files): void
    {
        $zip = new ZipArchive();
        if (! $zip->open($outputfile, ZipArchive::CREATE)) {
            throw new \Exception("Couldn't create archive ".$outputfile, 1);
        }
        foreach ($files as $name => $data) {
            $zip->addFromString($name, $data);
        }
        $zip->close();
    }

    /**
     * Writes files to a directory.
     * @param string $outputdir a valid directory
     * @param array $files asociative array where the key is the name and the value the data to write.
     */
    public function write(string $outputdir, array $files): void
    {
        foreach ($files as $name => $data) {
            file_put_contents($outputdir.DIRECTORY_SEPARATOR.$name, $data);
        }
    }
}
