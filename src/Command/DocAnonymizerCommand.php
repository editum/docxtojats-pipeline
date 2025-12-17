<?php

namespace App\Command;

use App\Form\UploadDocPdfFileFormType;
use App\Service\DocConversion\App\Anonymizer;
use App\Service\DocConversion\App\PdfConverter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DocAnonymizerCommand extends AbstractBatchDocConverterCommand
{
    const NAME = 'doc:anonymizer';
    const FORM_FIELD_FORMAT = UploadDocPdfFileFormType::NAME."[%s]";

    protected static $defaultName = self::NAME;
    protected static $defaultDescription = 'Converts the input file to PDF and strips its metadata';

    private PdfConverter $pdfConverter;
    private Anonymizer $anonymizer;

    public function __construct(
        PdfConverter $pdfConverter,
        Anonymizer $anonimizer,
        HttpClientInterface $httpclient,
        array $inputExtensions = ['odf', 'doc','docx'],
        string $outputExtension = 'pdf',
        ?string $remoteUrl = null,
        array $remoteOptions = [],
        int $dirmode = 0755,
        int $filemode = 0644
    ){
        $this->pdfConverter = $pdfConverter;
        $this->anonymizer = $anonimizer;
        parent::__construct(
            $inputExtensions,
            $outputExtension,
            $httpclient,
            $remoteUrl,
            $remoteOptions,
            $dirmode,
            $filemode
        );
    }

    public function localCallback(string $src, string $dst, bool $overwrite): bool
    {
        if (($this->pdfConverter)($src, $dst, $overwrite)) {
            return ($this->anonymizer)($dst);
        }
        return false;
    }
}
