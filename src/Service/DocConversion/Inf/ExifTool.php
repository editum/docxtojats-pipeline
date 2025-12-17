<?php

namespace App\Service\DocConversion\Inf;

use App\Service\DocConversion\Dom\ExternalCommand\ExifToolInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class ExifTool implements ExifToolInterface
{
    const CONVERSION_ERROR = 'Error reading/writing metadata';
    const INPUT_FILE_NOT_FOUND = 'Input file not found';

    private Filesystem $filesystem;
    private LoggerInterface $logger;
    private string $exiftool;
    private float $timeout;
    private float $throw;

    public function __construct(
        LoggerInterface $logger,
        string $exiftool = 'exiftool',
        float $conversionTimeout = 0,
        bool $throw = false
    ){
        $this->filesystem = new Filesystem();
        $this->logger = $logger;
        $this->exiftool = $exiftool;
        $this->timeout = $conversionTimeout;
        $this->throw = $throw;
        assert($this->timeout >= 0);
    }

    public function __invoke(string $input, string ...$options): bool
    {
        if (! $this->filesystem->exists($input)) {
            throw new InvalidArgumentException(self::INPUT_FILE_NOT_FOUND.": $input");
        }

        $cmd = array_merge([$this->exiftool], $options, [$input]);

        $process = new Process($cmd);
        if ($this->timeout) {
            $process->setTimeout($this->timeout);
        }

        try {
            $process->mustRun();
        } catch (ProcessFailedException | TimeoutException $exception) {
            $this->logger->error(self::CONVERSION_ERROR, [
                'command' => implode(' ', $cmd),
                'exception' => $exception->getMessage(),
            ]);
            if ($this->throw) {
                throw new RuntimeException(self::CONVERSION_ERROR.' '.$exception->getMessage());
            }
        }

        return $process->isSuccessful();
    }
}
