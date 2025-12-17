<?php

namespace App\Command;

use App\Service\DocConversion\Dom\FileExistsException;

// TODO This Trait should be merged with AbstractBatchDocConverterCommand
// TODO AbstractBatchDocConverterCommand should support the localCallback for JatsPublisherCommand
trait RemoteConverterTrait
{
    /**
     * Performs the conversion remotely.
     *
     * If the response content-type is application/zip and $this->archive is
     * false the file will be extracted to the parent destination directory.
     *
     * @param string $src file to convert that will be sent
     * @param string $dst destination path where the returned data will be written
     * @param bool $overwrite destination
     * @throws
     */
    public function remoteCallback(string $src, string $dst, bool $overwrite): bool
    {
        // Prepare the form
        $formDataPart = $this->formDataPart($src);
        $payload = [
            'headers' => $formDataPart->getPreparedHeaders()
                ->addHeader('X-Requested-With', 'XMLHttpRequest')
                ->addHeader('Accept', 'application/json,application/octet-stream,application/zip')
                ->toArray(),
            'body' => $formDataPart->bodyToIterable(),
        ];

        // Send the form
        $payload = array_merge_recursive($this->remoteOptions, $payload);
        $response = $this->client->request('POST', $this->remoteUrl, $payload);
        $status = $response->getStatusCode();

        // Handle error
        if ($status !== 200) {
            $error = "Error $status Processing Remote Request";
            $array = $response->toArray(false);
            if (isset($array['errors'])) {
                $error .= PHP_EOL.$this->flattenErrors($array['errors']);
            }
            throw new \RuntimeException($error, 1);
        }

        // TODO check if the file exists
        // Create the directory if it doesn't exists
        if ($dirname = pathinfo($dst)['dirname']) {
            if (! is_dir($dirname) && ! is_file($dirname)) {
                mkdir($dirname, $this->dirmode, true);
            }
        }

        // === Case: destination is not an archive
        if (! $this->archive && $response->getHeaders()['content-type'][0] === 'application/zip') {
            $tmpFile = tmpfile();
            $tmpMeta = stream_get_meta_data($tmpFile);
            $tmpPath = $tmpMeta['uri'];

            $this->logger->debug('Writing temporal ZIP file: '.$tmpPath);

            fwrite($tmpFile, $response->getContent());

            $zip = new \ZipArchive();
            if ($zip->open($tmpPath) !== true) {
                throw new \RuntimeException('Could not open temporal ZIP file.', 1);
            }
            fclose($tmpFile);

            // Check overwrite
            if (!$overwrite) {
                for ($i=0; $i < $zip->numFiles ; $i++) { 
                    $filename = $zip->getNameIndex($i);
                    $filepath = $dirname.DIRECTORY_SEPARATOR.$filename;
                    if (file_exists($filepath)) {
                        throw new FileExistsException($filepath);
                    }
                }
            }

            // Extract the files and set permissions
            $zip->extractTo($dirname);
            for ($i=0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $filepath = $dirname.DIRECTORY_SEPARATOR.$filename;
                $this->logger->debug('Extracting: '.$filepath);
                if (is_file($filepath)) {
                    chmod($filepath, $this->filemode);
                } elseif (is_dir($filepath)) {
                    chmod($filepath, $this->dirmode);
                }
            }
            $zip->close();
        }

        // === Case: Destination is an archive ===
        else {
            $this->logger->debug('Writing file to '.$dst);
            if (!$overwrite && file_exists($dst)) {
                throw new FileExistsException($dst);
            }
            file_put_contents($dst, $response->getContent());
            chmod($dst, $this->filemode);
        }
        return true;
    }

    /**
     * Flatten errors into a single string separated by PHP_EOL.
     *
     * @param mixed $errors
     * @return string
     */
    public function flattenErrors($errors): string
    {
        if ($errors === null) {
            return '';
        }

        // A string
        if (is_string($errors)) {
            return $errors;
        }

        // An array
        if (is_array($errors)) {
            $lines = [];
            foreach ($errors as $key => $value) {
                // argument: error / single error
                if (!is_array($value)) {
                    $lines[] = is_string($key) ? "{$key}: {$value}" : $value;
                // Just in case...
                } else {
                    foreach ($value as $v) {
                        $lines[] = is_string($key) ? "{$key}: {$v}" : $v;
                    }
                }
            }
            return implode(PHP_EOL, $lines);
        }

        // Fallback: cast to string
        return (string)$errors;
    }
}
