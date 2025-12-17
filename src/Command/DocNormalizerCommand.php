<?php

namespace App\Command;

use App\Form\UploadDocFileFormType;
use App\Service\DocConversion\App\DocxConverter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DocNormalizerCommand extends AbstractBatchDocConverterCommand
{
    const NAME = 'doc:normalizer';
    const FORM_FIELD_FORMAT = UploadDocFileFormType::NAME."[%s]";

    protected static $defaultName = self::NAME;
    protected static $defaultDescription = 'Clean and normalize documents';

    private DocxConverter $docxConverter;

    public function __construct(
        DocxConverter $docxConverter,
        HttpClientInterface $httpclient,
        array $inputExtensions = ['odf', 'doc','docx'],
        string $outputExtension = 'docx',
        ?string $remoteUrl = null,
        array $remoteOptions = [],
        int $dirmode = 0755,
        int $filemode = 0644
    ){
        $this->docxConverter = $docxConverter;
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
        return ($this->docxConverter)($src, $dst, $overwrite);
    }
}
