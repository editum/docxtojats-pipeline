<?php

namespace App\Command;

use App\Form\UploadZipFileType;
use App\Service\DocConversion\App\JatsPublisher;
use App\Service\DocConversion\App\JatsZipArchiver;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use SebastianBergmann\CodeCoverage\Report\PHP;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use ZipArchive;

class JatsPublisherCommand extends Command implements LoggerAwareInterface
{
    use RemoteConverterTrait;

    const DEBUG = true;

    const NAME                      = 'jats:publisher';

    const DESC_ARG_INPUTFILE        = 'An input document or a directory containing documents';
    const DESC_OPT_CREATE_DIR       = 'Create a directory for each file';
    const DESC_OPT_OVERWRITE        = 'Overwrite files, implies no-interactive';
    const DESC_OPT_LOCAL            = 'Run the command locally';

    const ARG_INPUTFILE             = 'inputFile';
    const OPT_EXIT_ON_ERROR         = 'exit-on-error';
    const OPT_LOCAL                 = 'local';
    const OPT_NON_INTERACTIVE       = 'no-interaction';
    const OPT_OVERWRITE             = 'overwrite';

    const FORM_FIELD_FORMAT         = UploadZipFileType::NAME."[%s]";

    protected static $defaultName = self::NAME;
    protected static $defaultDescription = 'Publish a jats document to html and pdf, this will create article.html, article.pdf and style.css files in the same folder as the input.';

    protected LoggerInterface $logger;

    /** @var array input extensions supported by the command */
    private array $inputExtensions;

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

    protected JatsPublisher $jatsPublisher;
    protected JatsZipArchiver $jatsZipArchiver;

    public function __construct(
        JatsPublisher $jatsPublisher,
        JatsZipArchiver $jatsZipArchiver,
        array $inputExtensions,
        ?HttpClientInterface $client = null,
        ?string $remoteUrl = null,
        array $remoteOptions = [],
        int $dirmode = 0755,
        int $filemode = 0644
    ){
        $this->jatsPublisher = $jatsPublisher;
        $this->jatsZipArchiver = $jatsZipArchiver;

        $this->inputExtensions = array_map('preg_quote', $inputExtensions);

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
            ->addOption(static::OPT_OVERWRITE, 'o', InputOption::VALUE_NONE, static::DESC_OPT_OVERWRITE)
        ;
        if ($this->remoteUrl) {
            $this->addOption(static::OPT_LOCAL, 'l', InputOption::VALUE_NONE, static::DESC_OPT_LOCAL);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $inputPath      = $input->getArgument(static::ARG_INPUTFILE);
        $overwrite      = $input->getOption(static::OPT_OVERWRITE);
        $interactive    = ! $input->getOption(static::OPT_NON_INTERACTIVE) && ! $overwrite;
        $useRemote      = $this->remoteUrl && ! $input->getOption(static::OPT_LOCAL);

        // === Choose callback ===
        /** @var callable(string,string,bool):bool $callback the conversion function to use */
        $remoteCallback = [$this, 'remoteCallback'];
        $localCallback = [$this, 'localCallback'];
        $callback = $useRemote ? $remoteCallback : $localCallback;

        $this->logger->debug(
            $useRemote ? 'Executing the command remotely' : 'Executing the command locally',
            compact('inputPath', 'overwrite', 'interactive')
        );

        try {
            if (! $callback($inputPath, Path::canonicalize($inputPath.'.zip'), $overwrite)) {
                throw new \RuntimeException("Error processing file", 1);
            }
        } catch (\Throwable $th) {
            $io->error("Error while processing file {$inputPath}: ". $th->getMessage());
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

    private function formDataPart(string $src): FormDataPart
    {
        $jatsArchive = ($this->jatsZipArchiver)($src);
        $formData = new FormDataPart([
            sprintf(static::FORM_FIELD_FORMAT, 'inputFile') => DataPart::fromPath($jatsArchive),
        ]);
        @unlink($jatsArchive);
        return $formData;
    }

    public function localCallback(string $src, ?string $dst, bool $overwrite): bool
    {
        return ($this->jatsPublisher)($src, $overwrite);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
