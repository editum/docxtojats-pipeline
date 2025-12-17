<?php

namespace App\Command;

use App\Form\DocToJatsFormType;
use App\Form\UploadDocPdfFileFormType;
use App\Service\DocConversion\App\JatsConverter;
use App\Service\DocConversion\App\JatsConverterOptions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DocToJatsCommand extends AbstractBatchDocConverterCommand
{
    const NAME = 'doc:tojats';
    const FORM_FIELD_FORMAT = UploadDocPdfFileFormType::NAME."[%s]";

    protected static $defaultName = self::NAME;
    protected static $defaultDescription = 'Converts a document to JATS XML';

    private JatsConverter $jatsConverter;
    private JatsConverterOptions $jatsConverterOptions;

    private ?string $bibliographyFile;
    private ?string $frontFile;

    // For remote execution use
    private FormFactoryInterface $formFactory;
    private FormInterface $form;
    private FormView $formView;

    public function __construct(
        JatsConverter $jatsConverter,
        JatsConverterOptions $jatsConverterOptions,
        FormFactoryInterface $formFactory,
        HttpClientInterface $httpclient,
        array $inputExtensions = ['odf', 'doc','docx'],
        string $outputExtension = 'xml',
        ?string $remoteUrl = null,
        array $remoteOptions = [],
        int $dirmode = 0755,
        int $filemode = 0644
    ){
        $this->jatsConverter = $jatsConverter;
        $this->jatsConverterOptions = $jatsConverterOptions;
        $this->formFactory = $formFactory;
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

    /**
     * Inhertis options from the parent.
     * Adds options to the command from JatsConverterOptions.
     */
    protected function configure(): void
    {
        // TODO las opciones de eliminar secciones y establecer el front no pertenecen a automark por lo que se deben desglosar aki
        parent::configure();
        foreach ($this->jatsConverterOptions->get() as $name => $option) {
            $shortcut = $option[JatsConverterOptions::K_SHORTCUT] ?? null;
            $description = $option[JatsConverterOptions::K_DESCRIPTION];
            $mode = InputOption::VALUE_NONE;
            $default = null;
            if (is_bool($option[JatsConverterOptions::K_VALUE])) {
                $mode = InputOption::VALUE_NONE;
                $default = null;
            } else {
                $mode = is_array($option[JatsConverterOptions::K_DEFAULT])
                    ? InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY
                    : InputOption::VALUE_REQUIRED;
                $default = $option[JatsConverterOptions::K_DEFAULT];
            }
            $this->addOption($name, $shortcut, $mode, $description, $default);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get jats converter options
        foreach ($this->jatsConverterOptions->getNames() as $option) {
            $value = $input->getOption($option);
            $this->jatsConverterOptions->set($option, $value);
        }

        // Archive output
        if ('zip' === (pathinfo($input->getArgument(self::ARG_OUTPUTFILE))['extension'] ?? '')) {
            $this->jatsConverterOptions->set(JatsConverterOptions::ARCHIVE, true);
            $this->archive = true;
        }
        if ($this->jatsConverterOptions->getValue(JatsConverterOptions::ARCHIVE)) {
            $this->setOutputExtension('zip');
            $this->archive = true;
        } else {
            $this->archive = false;
        }

        $this->bibliographyFile = $this->jatsConverterOptions->bibliographyFile ?? $this->jatsConverterOptions->txtBibliographyFile;
        $this->frontFile = $this->jatsConverterOptions->frontFile;

        return parent::execute($input, $output);
    }

    public function localCallback(string $src, string $dst, bool $overwrite): bool
    {
        $result = ($this->jatsConverter)($src, $dst, $overwrite);
        // Clean the csl file for next conversion if it was not passed to the command
        if (! $this->bibliographyFile) {
            $this->jatsConverterOptions->unset(JatsConverterOptions::BIBLIOGRAPHY_FILE);
        }
        return $result;
    }

    protected function formDataPart(string $src): FormDataPart
    {
        if (! isset($this->form)) {
            $this->form = $this->formFactory->create(DocToJatsFormType::class);
            $this->formView = $this->form->createView();
        }

        // Auxiliar function to obtain field full name
        $getFieldFullName = function (string $name): string {
            return $this->formView[$name]->vars['full_name'];
        };

        // Set all options with automark values
        $fields = [];
        foreach ($this->form as $field) {
            $name = $field->getName();
            $fullname = $getFieldFullName($name);
            try {
                $value = $this->jatsConverterOptions->getValue($name);
            } catch (\Throwable $th) {
                $value = $field->getData();
            }
            if (is_bool($value)) {
                //$value = $value ? 'true' : 'false';
                $value = $value ? 'true' : null;
            }
            elseif (is_array($value)) {
                $value = implode(' ', $value);
            }
            if (null === $value) {
                continue;
            }
            $fields[$fullname] = $value;
        }

        // Set files
        $fields[$getFieldFullName('inputFile')] = DataPart::fromPath($src);
        if ($this->bibliographyFile) {
            $fields[$getFieldFullName(JatsConverterOptions::BIBLIOGRAPHY_FILE)] = DataPart::fromPath($this->bibliographyFile);
        }
        if ($this->frontFile) {
            $fields[$getFieldFullName(JatsConverterOptions::FRONT_XML_FILE)] = DataPart::fromPath($this->frontFile);
        }

        return new FormDataPart($fields);
    }
}
