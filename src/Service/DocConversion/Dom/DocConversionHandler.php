<?php

namespace App\Service\DocConversion\Dom;

use InvalidArgumentException;
use Mimey\MimeTypes;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use UnexpectedValueException;

class DocConversionHandler implements LoggerAwareInterface
{
    private LoggerInterface $logger;

    /** @var bool will not delete tmp files when true */
    public bool $debug = false;

    /** @var int permissions for directories created */
    private int $dirmode;
    /** @var int permissions for files created */
    private int $filemode;
    /** @var string tmp prefix used with temporal files */
    private string $tmpPrefix;
    /** @var array temporal files to be deleted if it fails */
    private array $tmpfiles;

    // TODO not needed unless we add mimetype detection in input
    private MimeTypes $mimeTypes;

    private Filesystem $fs;

    public function __construct(int $dirmode = 0755, int $filemode = 0644)
    {
        $this->mimeTypes = new MimeTypes();
        $this->fs = new Filesystem();
        $this->dirmode = $dirmode;
        $this->filemode = $filemode;
        $this->tmpfiles = [];
        $this->tmpPrefix();
        $this->logger = new NullLogger();
    }

    public function __destruct()
    {
        $this->cleanTmpFiles();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * // TODO check mimetypes
     * // REVIEW remove returning success and use exceptions???
     * This is a wrapper to handle all the logic of input and output files.
     * It will create output directories.
     * The output file will be a temporal file in the same directory as the
     * output and will be moved to its final name if the operation is 
     * sucessfull.
     * If the output file exists, it will not be overwritten if the operation is
     * not successfull.
     * 
     * @param string $input
     * @param string $output
     * @param bool $overwrite true to overwrte file if exists
     * @param callable $callback function(string $input, string $outpu): bool
     * @return bool true if success
     * @throws FileNotFoundException when input file doesn't exists
     * @throws FileExistsException when the output file exists
     * @throws InvalidArgumentException when the input and output are the same, output not writable
     * @throws Throwable unexpected conversion handler behaivour, failed conversion
     */
    public function convert(string $input, string $output, bool $overwrite, callable $callback): bool
    {
        // REVIEW this probably is a closure, see if there is anyway to get class name or function name with reflection
        $handlerClass = get_class($callback);
        $logContext = compact('input', 'output', 'overwrite', 'handlerClass');

        $this->logger->info('Conversión handler started', $logContext);

        // Can't be the same file
        if ($input === $output) {
            throw new InvalidArgumentException('Input and output are the same.');
        }
        // Check input exists
        if (! $this->fs->exists($input)) {
            throw new FileNotFoundException($input);
        }
        // Check input is readable
        if (! is_readable($input)) {
            throw new FileNotReadableException($input);
        }
        // Check overwrite 
        if ($this->fs->exists($output)) {
            if (! $overwrite) {
                throw new FileExistsException($output);
            }
            if (! is_writable($output)) {
                throw new InvalidArgumentException('File is not writable: '.$output);
            }
        }

        // Create output path and create a backup file if necessary
        // REVIEW it's necessary $outputfile ??
        $outputdir = pathinfo($output, PATHINFO_DIRNAME) ?: './';
        $outputname = pathinfo($output, PATHINFO_BASENAME);
        $outputfile = $outputdir . DIRECTORY_SEPARATOR . $outputname;

        $newdirCreated = false;

        if ($this->fs->exists($outputdir)) {
            if (!is_dir($outputdir)) {
                throw new InvalidArgumentException("Output path is not a directory: $outputdir");
            }
            if (!is_writable($outputdir)) {
                throw new InvalidArgumentException("Directory is not writable: $outputdir");
            }
        } else {
            $this->fs->mkdir($outputdir, $this->dirmode);
            $newdirCreated = true;
        }

        // Perform conversion to temporary file
        $tmpfile = $this->newTmpFile($outputfile);
        $success = false;
        $rethrow = true;

        $this->logger->debug('Conversión handler temporal file: '.$tmpfile, $logContext);

        try {
            $success = $callback($input, $tmpfile);

            if ($success) {
                $this->fs->rename($tmpfile, $output, $overwrite);
                $this->fs->chmod($output, $this->filemode);
                $this->logger->info('Conversion handler success', $logContext);
            } else {
                $rethrow = false;
                throw new UnexpectedValueException("Handler returned false", 1);
            }

        } catch (\Throwable $th) {
            $this->logger->error('Conversion handler error: '.$th->getMessage(), $logContext);

            if ($newdirCreated) {
                $this->fs->remove($outputdir);
            }
            if ($rethrow) {
                throw $th;
            }
        }

        // NOTE: it's bad idea to clean files if nested call is expected, __destruct will handle it
        // $this->cleanTmpFiles();

        return $success;
    }

    /**
     * Adds a file or directory to the list of temporal files, which will be
     * deleted after the conversion is done.
     * @param string $tmpfile
     */
    public function addTmpFile(string $tmpfile): void
    {
        $this->tmpfiles[] = $tmpfile;
        $this->tmpfiles = array_unique($this->tmpfiles);
    }

    /**
     * Generates a new temporal filename and adds it to the list of files to be
     * deleted afterwards.
     * @param string $output output filename, can be a path
     * @param ?string $outputdir optional output directory, if null the output path will be used
     * @return string
     */
    public function newTmpFile(string $output, ?string $outputdir = null): string
    {
        // Remove the prefix from file if exists
        $outputname = str_replace($this->tmpPrefix, '', pathinfo($output)['basename']);
        $outputdir = $outputdir ?? pathinfo($output)['dirname'];
        rtrim($outputdir, DIRECTORY_SEPARATOR);
        do {
            $tmpfile = Path::normalize($outputdir.DIRECTORY_SEPARATOR.$this->tmpPrefix.$outputname);
            if (! $this->fs->exists($tmpfile)) {
                break;
            }
            $this->tmpPrefix();
        } while(true);

        $this->addTmpFile($tmpfile);
        return $tmpfile;
    }

    /**
     * Deletes all temporalfiles.
     */
    public function cleanTmpFiles(): void
    {
        if (empty($this->tmpfiles)) {
            return;
        }
        $this->logger->debug('Removing temporary files: ' . implode(' ', $this->tmpfiles));
        if (! $this->debug) {
            try {
                $this->fs->remove($this->tmpfiles);
            } catch (\Throwable $th) {
                $this->logger->warning('Unable to remove temporary files: ' . $th->getMessage());
                // throw $th;
            }
        }
        $this->tmpfiles = [];
    }

    public function mimeTypes(): MimeTypes
    {
        return $this->mimeTypes;
    }

    /**
     * Generartes a new tmp prefix for all temporal files.
     */
    private function tmpPrefix(): void
    {
        $this->tmpPrefix = '.~'.uniqid().'_';
    }
}
