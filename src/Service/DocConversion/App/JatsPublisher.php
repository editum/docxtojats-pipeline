<?php

namespace App\Service\DocConversion\App;

use App\Service\DocConversion\Dom\FileExistsException;
use App\Service\DocConversion\Dom\FileNotFoundException;
use App\Service\DocConversion\Dom\FileNotReadableException;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class JatsPublisher implements LoggerAwareInterface
{
    const CONVERSION_ERROR      = 'Error converting document.';
    const OUTPUT_FILE_NOT_FOUND = 'Output file not found';
    const OUTPUT_FILES = [ 
        'article.html',
        'article.pdf',
        'style.css',
    ];
    const REMOVE_FILES = [
        '.vivliostyle',
        'vivliostyle.config.js',
    ];

    private LoggerInterface $logger;
    private Filesystem $fs;

    /** @var string command which does the real conversion */
    private string $bin;
    private float $timeout;
    private bool $throw;

    public function __construct(
        string $bin,
        float $conversionTimeout = 0,
        bool $throw = false
    ){

        $this->bin = $bin;
        $this->timeout = $conversionTimeout;
        $this->throw = $throw;

        if (!is_executable($bin)) {
            throw new InvalidArgumentException("Script not executable: {$bin}");
        }

        $this->logger = new NullLogger();
        $this->fs = new Filesystem();
    }

    /**
     * Publish a JATS XML file in html and pdf.
     *
     * Creates 3 files in the same directory as the input:
     *  - article.html
     *  - article.pdf
     *  - style.css
     * 
     * @param string $input the input file, the output will be in the same path
     * @param bool $overwite true to overwrite files
     * @return bool true if success, if throw is true it will throw when error
     * @throws FileNotFoundException
     * @throws FileExistsException
     * @throws RuntimeException
     */
    public function __invoke(string $input, bool $overwrite): bool
    {
        // TODO try to use DocConversionHandler, using an array in ouput
        if (!file_exists($input)) {
            throw new FileNotFoundException($input);
        }
        if (!is_readable($input)) {
            throw new FileNotReadableException($input);
        }

        // Output and trash files
        $outputDir = Path::canonicalize(dirname($input));
        $outputFiles = array_map(function($f) use ($outputDir) {
            return Path::canonicalize($outputDir.DIRECTORY_SEPARATOR.$f);
        }, self::OUTPUT_FILES);
        $removeFiles = array_map(function($f) use ($outputDir) {
            return Path::canonicalize($outputDir.DIRECTORY_SEPARATOR.$f);
        }, self::REMOVE_FILES);

        // Check overwrite
        if (!$overwrite) {
            foreach ($outputFiles as $file) {
                if (file_exists($file))
                    throw new FileExistsException($file);
            }
        }

        // Prepare the command
        $cmd = [ $this->bin, $input ];
        $process = new Process($cmd);
        if ($this->timeout) {
            $process->setTimeout($this->timeout);
        }

        try {
            // Run the command
            $process->mustRun();
            foreach ($outputFiles as $file) {
                if (!file_exists($file)) {
                    throw new RuntimeException(self::OUTPUT_FILE_NOT_FOUND.": $file");
                }
            }
        } catch (ProcessFailedException $exception) {
            // Capture stderr on command failure
            $this->logger->error(self::CONVERSION_ERROR, [
                'command' => implode(' ', $cmd),
                'stderr'  => $process->getErrorOutput(),
                'stdout'  => $process->getOutput(),
                'exception' => $exception->getMessage(),
            ]);
            if ($this->throw) {
                throw new RuntimeException(
                    self::CONVERSION_ERROR.' '.$exception->getMessage()."\n".$process->getErrorOutput()
                );
            }
        } catch (TimeoutException | RuntimeException | IOException $exception) {
            // Other errors
            $this->logger->error(self::CONVERSION_ERROR, [
                'command' => implode(' ', $cmd),
                'exception' => $exception->getMessage(),
            ]);
            if ($this->throw) {
                throw new RuntimeException(self::CONVERSION_ERROR.' '.$exception->getMessage());
            }
        } finally {
            // Clean files
            $this->fs->remove($removeFiles);
        }

        return $process->isSuccessful();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
