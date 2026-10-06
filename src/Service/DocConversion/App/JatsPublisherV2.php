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
use ZipArchive;

class JatsPublisherV2 implements LoggerAwareInterface
{
    const CONVERSION_ERROR      = 'Error converting document.';

    private LoggerInterface $logger;
    private Filesystem $fs;

    /** @var string command to run the publisher script (node) */
    private string $nodeBin;
    
    /** @var string script path (index.js) */
    private string $scriptPath;

    /** @var string templates directory */
    private string $templatesDir;

    private float $timeout;
    private bool $throw;

    public function __construct(
        string $scriptPath = '/opt/doc2jats-pipeline/format-jats/bin/index.js',
        string $templatesDir = '/opt/doc2jats-pipeline/format-jats/templates',
        string $nodeBin = 'node',
        float $conversionTimeout = 0,
        bool $throw = false
    ){
        $this->scriptPath = $scriptPath;
        $this->templatesDir = $templatesDir;
        $this->nodeBin = $nodeBin;
        $this->timeout = $conversionTimeout;
        $this->throw = $throw;

        $this->logger = new NullLogger();
        $this->fs = new Filesystem();
    }

    /**
     * Publish a JATS XML file to html and pdf.
     *
     * @param string $inputXml the input xml file
     * @param string $theme the theme to use
     * @param string|null $configJsonPath path to config json file
     * @param string $format The output format (html, pdf, all)
     * @param bool $preview Generate a preview HTML wrapper
     * @return bool true if success
     */
    public function __invoke(string $inputXml, string $theme, ?string $configJsonPath, string $outputDir, string $format = 'all', bool $preview = false): bool
    {
        if (!file_exists($inputXml)) {
            throw new FileNotFoundException($inputXml);
        }
        if (!is_readable($inputXml)) {
            throw new FileNotReadableException($inputXml);
        }

        // Prepare the command
        $cmd = [ 
            $this->nodeBin, 
            $this->scriptPath, 
            $inputXml,
            '--theme', $theme,
            '--out', $outputDir,
            '--templates-dir', $this->templatesDir
        ];

        if ($format === 'html') {
            $cmd[] = '--html';
        } elseif ($format === 'pdf') {
            $cmd[] = '--pdf';
        } else {
            $cmd[] = '--all';
        }

        if ($preview) {
            $cmd[] = '--preview';
        }

        if ($configJsonPath && file_exists($configJsonPath)) {
            $cmd[] = '--config';
            $cmd[] = $configJsonPath;
        }

        $process = new Process($cmd);
        $process->setTimeout($this->timeout > 0 ? $this->timeout : 600);

        try {
            // Run the command
            $process->mustRun();
            $this->logger->info("format-jats output: " . $process->getOutput());
        } catch (ProcessFailedException $exception) {
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
            return false;
        } catch (TimeoutException | RuntimeException | IOException $exception) {
            // Other errors
            $this->logger->error(self::CONVERSION_ERROR, [
                'command' => implode(' ', $cmd),
                'exception' => $exception->getMessage(),
            ]);
            if ($this->throw) {
                throw new RuntimeException(self::CONVERSION_ERROR.' '.$exception->getMessage());
            }
            return false;
        }

        return $process->isSuccessful();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
