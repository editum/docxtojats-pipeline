<?php

namespace App\Service\Automark\Inf;

use App\Service\Automark\Dom\CslExtractorInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class AnystyleCslExtractor implements CslExtractorInterface
{
    const EXTRACTION_ERROR = 'Error extraticting citations.';
    const INPUT_FILE_NOT_FOUND = 'Input file not found';
    const INVALID_FORMAT = 'Invalid format';
    const VALID_OUTPUT_FORMATS = '/^(?:bib|csl|json|ref|txt|ttx|xml)$/';

    private Filesystem $filesystem;
    private LoggerInterface $logger;
    private string $bin;
    private float $timeout;
    private float $throw;

    public function __construct(
        LoggerInterface $logger,
        string $anystyle = 'anystyle',
        float $conversionTimeout = 0,
        bool $throw = false
    ){
        $this->filesystem = new Filesystem();
        $this->logger = $logger;
        $this->logger = $logger;
        $this->bin = $anystyle;
        $this->timeout = $conversionTimeout;
        $this->throw = $throw;
        assert($this->timeout >= 0);
    }

    /**
     * @throws InvalidArgumentException If input file does not exist or output formats are invalid
     * @throws RuntimeException If the process fails and $this->throw is true
     */
    public function __invoke(string $input, ?string $output, string ...$outputFormats)
    {
        if (! $this->filesystem->exists($input)) {
            throw new InvalidArgumentException(self::INPUT_FILE_NOT_FOUND.": $input");
        }
        if (empty($outputFormats)) {
            throw new InvalidArgumentException('Must specify a format');
        }
        foreach ($outputFormats as $format) {
            if (! preg_match(self::VALID_OUTPUT_FORMATS, $format)) {
                throw new InvalidArgumentException(self::INVALID_FORMAT.": $format");
            }
        }

        /* AnyStyle's `parse` command expects one complete reference per line in a .txt file.
           In our workflow, .txt files contain arbitrary text, not clean references,
           so we use the `find` command instead.
        */
        $anystyleCmd = in_array(pathinfo($input)['extension'] ?? 'noext', ['bib', 'csl', 'ref']) ? 'parse' : 'find';

        $cmd = array_merge([$this->bin, '-f'], $outputFormats, [$anystyleCmd, $input]);
        if ($output) {
            $cmd[] = $output;
        }

        $process = new Process($cmd);
        if ($this->timeout) {
            $process->setTimeout($this->timeout);
        }

        try {
            $process->mustRun();
        } catch (ProcessFailedException | TimeoutException $exception) {
            $this->logger->error(self::EXTRACTION_ERROR, [
                'command' => implode(' ', $cmd),
                'exception' => $exception->getMessage(),
            ]);
            if ($this->throw) {
                throw new RuntimeException(self::EXTRACTION_ERROR.' '.$exception->getMessage());
            }
        }

        // Return stdout
        if (! $output) {
            return $process->isSuccessful() ? $process->getOutput() : null;
        }
        // Return true|false
        return $process->isSuccessful();
    }
}
