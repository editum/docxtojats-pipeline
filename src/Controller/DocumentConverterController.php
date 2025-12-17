<?php

namespace App\Controller;

use App\Form\DocToJatsFormType;
use App\Form\UploadDocFileFormType;
use App\Form\UploadDocPdfFileFormType;
use App\Form\UploadZipFileType;
use App\Service\DocConversion\App\Anonymizer;
use App\Service\DocConversion\App\DocxConverter;
use App\Service\DocConversion\App\JatsConverter;
use App\Service\DocConversion\App\JatsPublisher;
use App\Service\DocConversion\App\PdfConverter;
use App\Service\DocConversion\App\JatsConverterOptions;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/doc", name="app_document_converter_")
 */
class DocumentConverterController extends AbstractController implements LoggerAwareInterface
{
    private LoggerInterface $logger;
    private Filesystem $filesystem;
    private EventDispatcherInterface $eventDispatcher;
    private string $tmpdir;

    public function __construct(EventDispatcherInterface $eventDispatcher, ?string $tmpdir = null)
    {
        $this->logger = new NullLogger();
        $this->filesystem = new Filesystem();
        $this->eventDispatcher = $eventDispatcher;
        $this->tmpdir = $tmpdir ?? sys_get_temp_dir().DIRECTORY_SEPARATOR.'conversions';
    }

    /**
     * Service health.
     * @Route("/", name="status")
     */
    public function index(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => 'It just works!',
        ]);
    }

    /**
     * Anonymizer.
     * Converts documents to PDF and strips all data.
     * @Route("/anonymizer", name="anonymizer")
     */
    public function anonymizer(Request $request, Anonymizer $anonymizer, PdfConverter $pdfConverter): Response
    {
        // Create the form
        $form = $this->createForm(UploadDocPdfFileFormType::class);
        $form->handleRequest($request);

        // Anonymize file
        $response = $this->downloadFile(
            $request,
            $form,
            'form.html.twig',
            [
                'title' => 'Anonymizer',
                'form' => $form
            ],
            function(UploadedFile $file, string $workdir) use ($anonymizer, $pdfConverter) :string {
                $ext = $file->getClientOriginalExtension();
                if ('pdf' === $ext) {
                    $outputfile = $file->getPathname();
                // Convert to pdf before anonymize
                } else {
                    $inputfile = $file->getPathname();
                    $outputfile = pathinfo($file->getClientOriginalName())['filename'].'.pdf';
                    $pdfConverter($inputfile, $outputfile);
                }
                $anonymizer($outputfile);
                return $outputfile;
        });
        return $response;
    }

    /**
     * Normalizer.
     * Converts/clean documents and returns a docx.
     * @Route("/normalizer", name="normalizer")
     */
    public function normalizer(Request $request, DocxConverter $normalizer): Response
    {
        // Create the form
        $form = $this->createForm(UploadDocFileFormType::class);
        $form->handleRequest($request);

        // Normalize file
        $response = $this->downloadFile(
            $request,
            $form,
            'form.html.twig',
            [
                'title' => 'Normalizer',
                'form' => $form
            ],
            function(UploadedFile $file, string $workdir) use ($normalizer) :string {
                $inputfile = $file->getPathname();
                $outputfile = pathinfo($file->getClientOriginalName())['filename'].'.docx';
                $normalizer($inputfile, $outputfile);
                return $outputfile;
        });
        return $response;
    }

    /**
     * Jats converter.
     * Converts documents to jats.
     * @Route("/tojats", name="tojats")
     */
    public function tojats(Request $request, JatsConverter $jatsConverter, JatsConverterOptions $options)
    {
        // Create the form
        $form = $this->createForm(DocToJatsFormType::class);
        $form->handleRequest($request);

        // Doc2jats
        $response = $this->downloadFile(
            $request,
            $form,
            'form.html.twig',
            [
                'title' => 'ToJats',
                'form' => $form
            ],
            function(UploadedFile $file , string $workdir) use ($jatsConverter, $options, $form) :string {
                $data = $form->getData();
                $normalize = $data['normalize'] ?? false;
                unset($data['normalize']);
                foreach ($data as $option => $value) {
                    $options->set($option, $value);
                }
                /** @var UploadedFile $biblographyFile the uploaded file with the references */
                if ($biblographyFile = $form->get(JatsConverterOptions::BIBLIOGRAPHY_FILE)->getData()) {
                    $filePath = $biblographyFile->getPathname();
                    // REVIEW: Anystyle is sensible to the extension: add the extension to the file
                    if ($ext = $biblographyFile->getClientOriginalExtension()) {
                        $tmp = "{$filePath}.{$ext}";
                        rename($filePath, $tmp);
                        $filePath = $tmp;
                        // Delete file when done
                        register_shutdown_function(function() use ($filePath) {
                            if (file_exists($filePath)) {
                                unlink($filePath);
                            }
                        });
                    }
                    $options->set(JatsConverterOptions::BIBLIOGRAPHY_FILE, $filePath);
                }
                /** @var UploadedFile $frontFile the uploaded file with the front */
                if ($frontFile = $form->get(JatsConverterOptions::FRONT_XML_FILE)->getData()) {
                    $options->set(JatsConverterOptions::FRONT_XML_FILE, $frontFile->getPathname());
                }
                $inputFile = $file->getPathname();
                $outputFile = pathinfo($file->getClientOriginalName())['filename'].'.zip';
                $options->archive = true;

                $jatsConverter($inputFile, $outputFile, $normalize, true);
                return $outputFile;
            }
        );

        return $response;
    }

    /**
     * Jats publisher.
     * Publishes a jats to html and pdf and returns a zip file.
     * NOTE: The xml must be in the root of the zip file.
     * @Route("/jatsPublisher", name="jatsPublisher")
     */
    public function jatsPublisher(Request $request, JatsPublisher $jatsPublisher)
    {
        // Create the form
        $form = $this->createForm(UploadZipFileType::class);
        $form->handleRequest($request);

        $response = $this->downloadFile(
            $request,
            $form,
            'form.html.twig',
            [
                'title' => 'JatsPublisher',
                'description' => 'Publish the jat document to html and pdf. The zip file must include the images.',
                'form' => $form
            ],
            function(UploadedFile $uploadedFile, string $workdir) use ($jatsPublisher) :string {
                // Extract archive to woking dir
                $inputZip = new \ZipArchive();
                if ($inputZip->open($uploadedFile->getPathname()) !== true) {
                    throw new \RuntimeException('Unable to open ZIP file');
                }
                if (!$inputZip->extractTo($workdir)) {
                    $inputZip->close();
                    throw new \RuntimeException('Failed to extract ZIP');
                }
                $inputZip->close();

                // Search the xml
                //$files = glob($workdir . DIRECTORY_SEPARATOR . '*.xml');
                $files = [];
                foreach (scandir($workdir) as $file) {
                    if (preg_match('/\.xml$/i', $file)) {
                        $files[] = $workdir . DIRECTORY_SEPARATOR . $file;
                    }
                }

                if (empty($files)) {
                    throw new \RuntimeException("No JATS XML document found in ZIP file");
                }

                if (count($files) > 1) {
                    throw new \RuntimeException('Only one JATS XML document was expected');
                }

                $jatsFile = $files[0];

                // Publish
                $jatsPublisher($jatsFile, true);

                // Archive the output
                $outputName = basename($jatsFile, '.xml') . '.zip';
                $outputZip = new \ZipArchive();

                if (! $outputZip->open($outputName, \ZipArchive::CREATE)) {
                    throw new \RuntimeException('Failed to create ZIP file');
                }

                foreach (JatsPublisher::OUTPUT_FILES as $f) {
                    $outputZip->addFile($workdir . DIRECTORY_SEPARATOR . $f, $f);
                }

                $outputZip->close();

                return $outputName;
            }
        );

        return $response;
    }

    /**
     * Auxiliar function to get the 'inputFile' from a form and return a file calling a callback function.
     * It will create a temporal directory and change to it.
     * The file in the response will have the name returned by the callback.
     * Flags url:
     *  - noattachment: will not return the file as an attachment
     *
     * @param Request $request
     * @param FormInterface $form
     * @param string $view template to render
     * @param array $parameters with the template parameters
     * @param callable $callback function (UploadedFile $file, string $workdir): string with the output file
     * @param ?callable $onFailure alternative callable to render errors
     * @return null|JsonResponse|BinaryFileResponse null when the form is not submitted, JsonResponse on error
     */
    private function downloadFile(
        Request $request,
        FormInterface $form,
        string $view,
        array $parameters,
        callable $callback,
        ?callable $onFailure = null
    ): ?Response {
        // Consider is an api call when the header Accept contains application/json
        $acceptTypes = array_map('trim', explode(',', $request->headers->get('Accept')));
        $isApiCall = false;
        foreach ($acceptTypes as $type) {
            if (str_starts_with($type, 'application/json')) {
                $this->logger->debug('Api call');
                $isApiCall = true;
                break;
            }
        }

        // Function to show errors, it will render a json or template depending onf the header Accept
        if (! $onFailure) {
            $onFailure = function ($message, int $code = Response::HTTP_BAD_REQUEST) use ($isApiCall, $form, $view, $parameters): Response {
                if ($isApiCall) {
                    return $this->json(['success' => false, 'errors' => $message], $code);
                }
                if (is_string($message)) {
                    $form->addError(new FormError($message));
                }
                return $this->renderForm($view, $parameters);
            };
        }

        // Render form
        if (! $form->isSubmitted()) {
            if ($isApiCall) {
                $this->logger->error('Form not submitted.');
                return $onFailure('Form not submitted.', Response::HTTP_BAD_REQUEST);
            }
            return $this->renderForm($view, $parameters);
        }

        // Render form with errors
        // TODO add validation callback
        if (! $form->isValid()) {
            $errors = $this->getFormErrors($form);
            if ($isApiCall) {
                try {
                    $this->logger->error(json_encode($errors));
                } catch (\Throwable $th) {
                    $this->logger->error(serialize($errors));
                }
            }
            return $onFailure($errors, Response::HTTP_BAD_REQUEST);
        }

        $data = $form->getData();
        /** @var UploadedFile the uploaded file */
        $inputFile = $form->get('inputFile')->getData();
        /** @var string original uploaded file name */
        $name = $inputFile->getClientOriginalName();
        /** @var string current temporal working directory */
        $workdir = $this->tmpdir.DIRECTORY_SEPARATOR.pathinfo($inputFile->getPathname())['basename'];
        $workdir = Path::canonicalize($workdir);
        /** @var array for log purposes */
        $logContext = [
            'name' => $name,
            'file' => $inputFile->getPathname(),
            'work_dir' => $workdir,
        ];

        // Create and chdir to work directory, it will deleted when the work is done
        try {
            $this->filesystem->mkdir($workdir);
            if (! chdir($workdir)) {
                throw new \Exception("Unable to set current directory.", 1);
            }
            // Delete the working directory after the file has been sent
            $this->eventDispatcher->addListener(KernelEvents::TERMINATE, function () use ($workdir, $logContext) {
                $this->logger->debug('Cleaning conversion working directory.', $logContext);
                $this->filesystem->remove($workdir);
            });
        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());
            return $onFailure('Error creating temporal working directory.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Do the conversion using the callback
        try {
            $this->logger->info('Starting conversion.', $logContext);
            $outputFile = $callback($inputFile, $workdir);
            if (! $this->filesystem->exists($outputFile)) {
                throw new \RuntimeException('Error converting file.');
            }
        } catch (\Throwable $th) {
            // Conversion failed
            $errors = $this->getCommandErrors($th->getMessage());
            $this->logger->error($errors, $logContext);
            return $onFailure($errors, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Conversion successful, return the file as attachment with same name
        $this->logger->info('Conversion success: '.$outputFile, $logContext);
        $response = (new BinaryFileResponse($outputFile))->deleteFileAfterSend(true);
        return $isApiCall ? $response : $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $outputFile);
    }

    /**
     * Cleans the error output from a command.
     * @param string $str the error
     * @return string
     */
    private function getCommandErrors(string $errors): string
    {
        return trim(str_replace('\n','\\n', $errors));
    }

    /**
     * Gets all errors from the form.
     */
    private function getFormErrors(FormInterface $form): array
    {
        $errors = [];
        
        // Obtener errores de los campos del formulario
        foreach ($form->all() as $child) {
            if ($child->isSubmitted() && !$child->isValid()) {
                $errors[$child->getName()] = array_map(
                    fn($error) => $error->getMessage(),
                    iterator_to_array($child->getErrors())
                );
            }
        }

        // Obtener errores globales del formulario
        foreach ($form->getErrors() as $error) {
            $errors['form'][] = $error->getMessage();
        }

        return $errors;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
