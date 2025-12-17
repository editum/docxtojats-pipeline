<?php

namespace App\Command;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use ZipArchive;

abstract class AbstractBatchDocConverterCommand extends Command implements LoggerAwareInterface
{
    use RemoteConverterTrait;

    const DEBUG = false;

    const DESC_ARG_INPUTFILE        = 'An input document or a directory containing documents';
    const DESC_ARG_OUTPUTFILE       = 'Output document or directory';
    const DESC_OPT_CREATE_DIR       = 'Create a directory for each file';
    const DESC_OPT_OVERWRITE        = 'Overwrite files, implies no-interactive';
    const DESC_OPT_EXIT_ON_ERROR    = 'Abort operation when there is an error in one file';
    const DESC_OPT_LOCAL            = 'Run the command locally';

    const ARG_INPUTFILE         = 'inputFile';
    const ARG_OUTPUTFILE        = 'outputFile';
    const OPT_CREATE_DIR        = 'create-dir';
    const OPT_EXIT_ON_ERROR     = 'exit-on-error';
    const OPT_LOCAL             = 'local';
    const OPT_NON_INTERACTIVE   = 'no-interaction';
    const OPT_OVERWRITE         = 'overwrite';

    /** @var string used to format fields in formDataPart */
    const FORM_FIELD_FORMAT     = '%s';

    /** @var array input extensions supported by the command */
    private array $inputExtensions;
    /** @var string extension for the output files */
    private string $outputExtension;

    // These variables are used with the remoteCallback
    /** @var bool $archive used in remote calls to unzip when needed */
    protected bool $archive;
    protected ?HttpClientInterface $client;
    /** @var ?string if defined the command will be executed remotely */
    protected ?string $remoteUrl;
    /** @var array HttpClient options for remote execution */
    protected array $remoteOptions;
    /** @var int directory creation permissions */
    protected int $dirmode;
    /** @var int file creation permission */
    protected int $filemode;

    protected LoggerInterface $logger;

    public function __construct(
        array $inputExtensions,
        string $outputExtension,
        ?HttpClientInterface $client = null,
        ?string $remoteUrl = null,
        array $remoteOptions = [],
        int $dirmode = 0755,
        int $filemode = 0644
    ){

        $this->inputExtensions = array_map('preg_quote', $inputExtensions);
        $this->outputExtension = preg_quote($outputExtension);

        $this->client = $client;
        $this->remoteUrl = $remoteUrl;
        $this->remoteOptions = $remoteOptions;
        $this->dirmode = $dirmode;
        $this->filemode = $filemode;
        $this->archive = false;

        $this->logger = new NullLogger();

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(static::ARG_INPUTFILE, InputArgument::REQUIRED, static::DESC_ARG_INPUTFILE)
            ->addArgument(static::ARG_OUTPUTFILE, InputArgument::REQUIRED, static::DESC_ARG_OUTPUTFILE)
            ->addOption(static::OPT_CREATE_DIR, 'd', InputOption::VALUE_NONE, static::DESC_OPT_CREATE_DIR)
            ->addOption(static::OPT_OVERWRITE, 'o', InputOption::VALUE_NONE, static::DESC_OPT_OVERWRITE)
            ->addOption(static::OPT_EXIT_ON_ERROR, 'a', InputOption::VALUE_NONE, static::DESC_OPT_EXIT_ON_ERROR)
        ;
        if ($this->remoteUrl) {
            $this->addOption(static::OPT_LOCAL, 'l', InputOption::VALUE_NONE, static::DESC_OPT_LOCAL);
        }
    }

    /**
     * Executes the current command.
     * This function only calls the mehotd batchConversion.
     * Override this function if you need more freedom to call batchConversion.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->batchConversion($input, $output);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getOutputExtension(): string
    {
        return $this->outputExtension;
    }

    public function setOutputExtension(string $ext): self
    {
        $this->outputExtension = $ext;
        return $this;
    }

    /**
     * Converts one or more files, either locally or remotely, depending on the options.
     * 
     * This method implements the main batch conversion logic:
     *  - If the property remoteUrl is configured and the option static::OPT_LOCAL is not set, the
     *    conversion will be delegated to the remote service.
     *  - Otherwise, the conversion will be performed locally.
     *  - Supports single file or directory batch processing.
     *
     * A local or remote callback can be provided to override the default behavior.
     *
     * @param InputInterface                           $input          Input file or directory
     * @param OutputInterface                          $output         Output path
     * @param (callable(string,string,bool):bool)|null $localCallback  Callable for local conversion
     * @param (callable(string,string,bool):bool)|null $remoteCallback Callable for remote conversion
     * 
     * @return int Command success or not
     */
    public function batchConversion(
        InputInterface $input,
        OutputInterface $output,
        ?callable $localCallback = null,
        ?callable $remoteCallback = null
    ): int {
        $start_time = time();
        $io = new SymfonyStyle($input, $output);

        // === Read parameters and options ===
        $inputPath      = $input->getArgument(static::ARG_INPUTFILE);
        $outputPath     = $input->getArgument(static::ARG_OUTPUTFILE);
        $overwrite      = $input->getOption(static::OPT_OVERWRITE);
        $interactive    = ! $input->getOption(static::OPT_NON_INTERACTIVE) && ! $overwrite;
        $abortOnFailure = $input->getOption(static::OPT_EXIT_ON_ERROR);
        $createFileDir  = $input->getOption(static::OPT_CREATE_DIR);
        $useRemote      = $this->remoteUrl && ! $input->getOption(static::OPT_LOCAL);

        $options = compact('inputPath', 'outputPath', 'overwrite', 'interactive', 'abortOnFailure', 'createFileDir', 'remoteCallback');

        // === Choose callback ===
        /** @var callable(string,string,bool):bool $callback the conversion function to use */
        $callback = $useRemote
            ? ($remoteCallback ?? [$this, 'remoteCallback'])
            : ($localCallback ?? [$this, 'localCallback']);

        $this->logger->debug(
            $useRemote ? 'Executing the command remotely' : 'Executing the command locally',
            $options
        );

        // === Resolve absolute paths ===
        $cwd = getcwd();
        $src = Path::makeAbsolute($inputPath, $cwd);
        $srcinfo = pathinfo($src);
        $srcfiles = [];

        $dst = Path::makeAbsolute($outputPath, $cwd);
        $dstinfo = pathinfo($dst);
        $dstfiles = [];

        // === Case: one file ===
        if (is_file($src) && is_readable($src)) {
            $srcdir = $srcinfo['dirname'];
            $srcfiles[] = $srcinfo['basename'];

            if (!empty($dstInfo['extension'])) {
                $dstdir = $dstinfo['dirname'];
                $dstfiles[] = $dstinfo['basename'];
            } else {
                $dstdir = $dst;
                $dstfiles[] = $srcinfo['filename'].'.'.$this->outputExtension;
            }
        // === Case: directory ====
        } elseif (is_dir($src) && is_readable($src)) {
            if (file_exists($dst) && (! is_dir($dst) || ! is_writable($dst))) {
                $io->error('The output must be a writable directory when the input is a directory.');
                return Command::INVALID;
            }

            $srcdir = $src;
            $dstdir = $dst;
            $regexInputExtensions = '/^(?:'.implode('|', $this->inputExtensions).')$/ui';

            foreach (scandir($srcdir) as $basename) {
                $info = pathinfo($basename);
                if (!empty($info['extension']) && preg_match($regexInputExtensions, $info['extension'])) {
                    $srcfiles[] = $info['basename'];
                    $dstfiles[] = $info['filename'].'.'.$this->outputExtension;
                }
            }
        // === Case: not found / readable ===
        } else {
            $io->error("The input does not exists or is not readable.");
            return Command::INVALID;
        }

        if (empty($srcfiles)) {
            $io->error('Nothing to do.');
            return Command::INVALID;
        }

        assert(count($srcfiles) == count($dstfiles));

        // === Processing ===

        $helper         = $this->getHelper('question');
        $askOverwrite   = new ConfirmationQuestion('Overwrite output file? ', false);
        $orgOverwrite   = $overwrite;
        $countErrors    = 0;
        $countFiles     = count($srcfiles);

        for ($i=0; $i < $countFiles ; $i++) {
            $overwrite = $orgOverwrite;

            $src = Path::canonicalize($srcdir.DIRECTORY_SEPARATOR.$srcfiles[$i]);
            $dst = ! $createFileDir
                ? Path::canonicalize($dstdir.DIRECTORY_SEPARATOR.$dstfiles[$i])
                : Path::canonicalize($dstdir.DIRECTORY_SEPARATOR.pathinfo($src, PATHINFO_FILENAME).DIRECTORY_SEPARATOR.$dstfiles[$i]);

            // Overwrite?
            if (file_exists($dst)) {
                $io->warning("Output file exists: {$dst}");
                if ($interactive && ! $overwrite) {
                    $overwrite = $helper->ask($input, $output, $askOverwrite);
                }
                if (!$overwrite) {
                    continue;
                }
            }

            // Conversion
            try {
                if (! $callback($src, $dst, $overwrite)) {
                    throw new RuntimeException("Error processing file", 1);
                }
            } catch (\Throwable $th) {
                if (self::DEBUG) {
                    throw $th;
                }
                $io->error("Error while processing file {$src}: ". $th->getMessage());
                if ($abortOnFailure) {
                    $io->error('Operation aborted');
                    return Command::FAILURE;
                }
                $countErrors++;
            }
        }

        // === Return operation info ===
        $time = time() - $start_time;
        $msg = "Total files processed: {$countFiles}, Errors: {$countErrors}, Time: {$time}s";

        if (!$countErrors) {
            $io->success($msg);
        } elseif ($countErrors < $countFiles) {
            $io->warning($msg);
        } else {
            $io->error($msg);
        }

        return $countErrors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Create a multipar/form-data used to call the remote command.
     * This function uses static::FORM_FIELD_FORMAT to format the form fields.
     * @param string $src file to convert that will be sent
     * @return FormDataPart
     */
    protected function formDataPart(string $src): FormDataPart
    {
        return new FormDataPart([
            //sprintf(static::FORM_FIELD_FORMAT, '_token') => ...,
            sprintf(static::FORM_FIELD_FORMAT, 'inputFile') => DataPart::fromPath($src),
        ]);
    }

    /**
     * Performs the conversion localky.
     *
     * Implement this function if you don't pretend to pass a local callback to
     * the method batchConversion.
     *
     * @param string $src file to convert
     * @param string $dst destination path where the data will be written
     * @param bool $overwrite destination
     */
    public function localCallback(string $src, string $dst, bool $overwrite): bool
    {
        throw new RuntimeException("Method LocalCallback Not implemented", 1);
    }
}
