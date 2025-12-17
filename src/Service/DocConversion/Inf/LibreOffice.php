<?php

namespace App\Service\DocConversion\Inf;

use App\Service\DocConversion\Dom\ExternalCommand\LibreOfficeInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class LibreOffice implements LibreOfficeInterface
{
    const CONVERSION_ERROR = 'Error converting document.';
    const INPUT_FILE_NOT_FOUND = 'Input file not found';
    const OUTPUT_FILE_NOT_FOUND = 'Output file not found';
    const HOME_ERROR = 'LibreOffice home folder is not writable';

    private Filesystem $filesystem;
    private LoggerInterface $logger;
    private string $bin;
    private string $home;
    private float $timeout;
    private float $throw;

    public function __construct(
        LoggerInterface $logger,
        string $libreoffice = 'libreoffice',
        string $libreofficeHome = '/tmp/libreoffice',
        float $conversionTimeout = 0,
        bool $throw = false
    ){
        $this->filesystem = new Filesystem();
        $this->logger = $logger;
        $this->bin = $libreoffice;
        $this->home = $libreofficeHome;
        $this->timeout = $conversionTimeout;
        $this->throw = $throw;
        assert($this->timeout >= 0);
    }

    public function __invoke(string $input, string $output, string $to): bool
    {
        if (! $this->filesystem->exists($input)) {
            throw new InvalidArgumentException(self::INPUT_FILE_NOT_FOUND.": $input");
        }

        // Create temporal directory because libreoffice only accepts a directory as output
        do {
            $tmpdir = Path::normalize(sys_get_temp_dir().'/'.uniqid('libreoffice_', true));
        } while($this->filesystem->exists($tmpdir));
        $this->filesystem->mkdir($tmpdir);

        $cmd = [
            $this->bin, '--headless', '--convert-to', $to, '--outdir', $tmpdir, $input
        ];

        if (is_dir($this->home) && !is_writable($this->home)) {
            throw new RuntimeException(self::HOME_ERROR, 1);
        }

        $process = new Process($cmd, null, ['HOME' => $this->home]);
        if ($this->timeout) {
            $process->setTimeout($this->timeout);
        }

        try {
            $process->mustRun();
            $convertedFile = $tmpdir.'/'.pathinfo($input)['filename'].'.'.$to;
            if (! $this->filesystem->exists($convertedFile)) {
                throw new RuntimeException(self::OUTPUT_FILE_NOT_FOUND.": $convertedFile");
            }
            $this->filesystem->rename($convertedFile, $output);
        } catch (ProcessFailedException | TimeoutException | RuntimeException | IOException $exception) {
            $this->logger->error(self::CONVERSION_ERROR, [
                'command' => implode(' ', $cmd),
                'exception' => $exception->getMessage(),
            ]);
            if ($this->throw) {
                throw new RuntimeException(self::CONVERSION_ERROR.' '.$exception->getMessage());
            }
        } finally {
            $this->filesystem->remove($tmpdir);
        }

        return $process->isSuccessful();
    }
}
