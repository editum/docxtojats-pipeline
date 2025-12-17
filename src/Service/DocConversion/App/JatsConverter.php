<?php

namespace App\Service\DocConversion\App;

use App\Service\Automark\App\Automark;
use App\Service\Automark\App\CslGenerator;
use App\Service\DocConversion\Dom\DocConversionHandler;
use App\Service\DocConversion\Dom\DocxToJats;
use Psr\Log\LoggerInterface;

final class JatsConverter
{
    private JatsConverterOptions $options;
    private DocConversionHandler $conversionHandler;
    private Automark $automark;
    private CslGenerator $cslGenerator;
    private DocxConverter $docxConverter;
    private PdfConverter $pdfConverter;
    private FileWriter $fileWriter;
    private LoggerInterface $logger;

    public function __construct(
        JatsConverterOptions $options,
        DocConversionHandler $conversionHandler,
        Automark $automark,
        CslGenerator $cslGenerator,
        DocxConverter $docxConverter,
        PdfConverter $pdfConverter,
        FileWriter $fileWriter,
        LoggerInterface $logger
    ){
        $this->options = $options;
        $this->conversionHandler = $conversionHandler;
        $this->automark = $automark;
        $this->cslGenerator = $cslGenerator;
        $this->docxConverter = $docxConverter;
        $this->pdfConverter = $pdfConverter;
        $this->fileWriter = $fileWriter;
        $this->logger = $logger;
    }

    /**
     * Converts input file to jats.
     * The file and all it's assets will be created in the same directory as the output.
     * 
     * @param string $input docx file
     * @param string $output
     * @param JatsConverterOptions $options
     *
     * @return bool true if success
     *
     * @throws FileNotFoundException
     * @throws InvalidArgumentException
     */
    public function __invoke(
        string $input,
        string $output,
        bool $overwrite = false
    ): bool {
        $inputOrg = $input;
        $outpathinfo = pathinfo($output);
        /** @var string destination filename */
        $filename = $outpathinfo['filename'];
        /** @var string destination directory */
        $outputdir = $outpathinfo['dirname'].DIRECTORY_SEPARATOR;

        return $this->conversionHandler->convert($input, $output, $overwrite, function($input, $output) use ($filename, $outputdir): bool {
            // STEP 1: normalize document, it will change input
            if ($this->options->normalize) {
                $normalizedFile = $this->conversionHandler->newTmpFile($outputdir.$filename.'_normalized.docx');
                $this->logger->debug('Intermediate normalized DOCX file: '.$normalizedFile);
                if (! ($this->docxConverter)($input, $normalizedFile, true)) {
                    return false;
                }
                $input = $normalizedFile;
            }

            // STEP 2: docToJats
            $this->logger->debug('Converting to xml from: '.$input);
            $docxToJats = new DocxToJats($input);

            // STEP 3: extract CSL or use the one loaded in automark options
            // REVIEW if we don't want the csl to be saved with extra information, we need to clone it before automark
            $csl = null;
            if ($this->options->citationStyle) {
                // The CSL is in a JSON file
                if (isset($this->options->csl)) {
                    $csl = $this->options->csl;
                } else {
                    $csl = ($this->cslGenerator)($docxToJats->jatsDom);
                }

                // Falllback: Convert and extract bibliography from an intermediate PDF file
                if (empty($csl)) {
                    // REVIEW if conversion to pdf fails should throw ??
                    $pdfFile = $this->conversionHandler->newTmpFile($outputdir.$filename.'_csl.pdf');
                    if (($this->pdfConverter)($input, $pdfFile, true)) {
                        $csl = ($this->cslGenerator)($pdfFile);
                    }
                }

                if (empty($csl)) {
                    $this->logger->warning('CSL is empty');
                }
            }

            // STEP 4: automark
            $this->logger->debug('Calling automark...');
            $generateBibliography = ! empty($this->options->citationStyle) && ! empty($csl);

            ($this->automark)(
                $docxToJats->jatsDom,
                $this->options->citationStyle,
                $csl,
                $generateBibliography,
                $this->options->generateScieloMixedCitations && $generateBibliography,
                $generateBibliography,
                $this->options->setFiguresTitles,
                $this->options->setTablesTitles,
                $this->options->replaceTitlesWithReferences
            );

            // STEP 5: replace/set front
            if ($this->options->front) {
                $this->logger->debug('Replacing <front>');
                $docxToJats->setFront($this->options->front);
            }
            
            // STEP 6: remove sections
            if (! empty($this->options->removeSectionsIds)) {
                $this->logger->debug('Removing <section>: "'.implode('", "', $this->options->removeSectionsIds).'"');
                $docxToJats->removeSectionsById(...$this->options->removeSectionsIds);
            }

            // Prepare files, the xml name depends if it will be archived or not
            $files = [];
            $files[$this->options->archive ? $filename.'.xml' : pathinfo($output)['basename']] = $docxToJats->getContents();
            $files = array_merge($files, $docxToJats->getMediaFiles());

            if ($csl) {
                $files[$filename.'.json'] = json_encode($csl, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            // Write to fs or archive
            if ($this->options->archive) {
                $this->fileWriter->archive($output, $files);
            } else {
                $this->fileWriter->write($outputdir, $files);
            }

            return true;
        });
    }
}
