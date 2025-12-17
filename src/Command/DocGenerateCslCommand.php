<?php

namespace App\Command;

use App\Service\Automark\App\CslGenerator;
use App\Service\DocConversion\App\PdfConverter;
use App\Service\DocConversion\App\TxtConverter;
use App\Service\DocConversion\Dom\FileNotFoundException;
use Mimey\MimeTypes;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class DocGenerateCslCommand extends Command
{
    const NAME = 'doc:generate:csl';

    protected static $defaultName = self::NAME;
    protected static $defaultDescription = 'Generates the bibliography in CSL format from a file';

    private CslGenerator $cslGenerator;
    private MimeTypes $mimeTypes;
    private PdfConverter $pdfConverter;
    private TxtConverter $txtConverter;

    public function __construct(CslGenerator $cslGenerator, PdfConverter $pdfConverter, TxtConverter $txtConverter)
    {
        $this->cslGenerator = $cslGenerator;
        $this->mimeTypes = new MimeTypes();
        $this->pdfConverter = $pdfConverter;
        $this->txtConverter = $txtConverter;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('inputFile', InputArgument::REQUIRED, 'An input document or a directory containing documents')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $inputfile = $input->getArgument('inputFile');

        $fs = new Filesystem();
        if (! $fs->exists($inputfile)) {
            $th = new FileNotFoundException($inputfile);
            $io->error($th->getMessage());
            return Command::INVALID;
        }

        if (! $ext = pathinfo($inputfile)['extension'] ?? null) {
            $ext = $this->mimeTypes->getExtension(mime_content_type($inputfile));
        }

        $tmpoutput = null;
        switch ($ext) {
            case 'pdf':
            case 'txt':
                $tmpoutput = null;
                break;
            case 'xml':
                $tmpoutput = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid().pathinfo($inputfile)['basename'].'.txt';
                ($this->txtConverter)($inputfile, $tmpoutput, true, 'jats');
                break;
            default:
                $tmpoutput = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid().pathinfo($inputfile)['basename'].'.pdf';
                ($this->pdfConverter)($inputfile, $tmpoutput);
                break;
        }

        if ($tmpoutput) {
            $csl = ($this->cslGenerator)($tmpoutput);
            $fs->remove($tmpoutput);
        } else {
            $csl = ($this->cslGenerator)($inputfile);
        }

        if ($csl) {
            if ($json = json_encode($csl, JSON_PRETTY_PRINT)) {
                echo $json;
                return Command::SUCCESS;
            }
        }
        return Command::FAILURE;
    }
}
