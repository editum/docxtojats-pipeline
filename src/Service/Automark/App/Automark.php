<?php

namespace App\Service\Automark\App;

use App\Component\Xml\XmlUtils;
use App\Service\Automark\Dom\BibliographyGenerator;
use App\Service\Automark\Dom\CitationGenerator;
use App\Service\Automark\Dom\CitationStyle\CitationStyleFactory;
use App\Service\Automark\Dom\CslRepositoryInterface;
use App\Service\Automark\Dom\Caption\AbstractCaptionSetter;
use App\Service\Automark\Dom\Caption\FiguresCaptionSetter;
use App\Service\Automark\Dom\Caption\TablesCaptionSetter;
use DOMDocument;
use DOMXPath;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class Automark implements LoggerAwareInterface
{
    /** @var string search query to be used when replacing substrings with xref nodes */
    const SUBS_QUERY = <<<'XPATH'
    /article/body//p//text()[not(ancestor::xref)] |
    /article/front//abstract//p//text()[not(ancestor::xref)] |
    /article/body//td[not(.//p)]//text()[not(ancestor::xref)]
    XPATH;

    private LoggerInterface $logger;

    private CslRepositoryInterface $cslRepository;

    private BibliographyGenerator $bibliographyGenerator;
    private CitationGenerator $citationGenerator;
    private CitationStyleFactory $citationStyleFactory;
    private TablesCaptionSetter $tablesCaptionSetter;
    private FiguresCaptionSetter $figuresCaptionSetter;

    public function __construct(
        CslRepositoryInterface $cslRepository,
        BibliographyGenerator $bibliographyGenerator,
        CitationGenerator $citationGenerator,
        CitationStyleFactory $citationStyleFactory,
        FiguresCaptionSetter $figuresCaptionSetter,
        TablesCaptionSetter $tablesCaptionSetter
    ){
        $this->logger = new NullLogger();
        $this->bibliographyGenerator = $bibliographyGenerator;
        $this->citationGenerator = $citationGenerator;
        $this->citationStyleFactory = $citationStyleFactory;
        $this->cslRepository = $cslRepository;
        $this->figuresCaptionSetter = $figuresCaptionSetter;
        $this->tablesCaptionSetter = $tablesCaptionSetter;
    }

    /**
     * @param DOMDocument $dom the JATS document
     * @param ?string $citationStyleName the citation style used in the document
     * @param ?array $cslReferences the document citation style language references
     * @param bool $generateBibliography add the cslReferences as bibliography
     * @param bool $generateBibliographyMixedCitations add mixed citations to the bibliography for Scielo compatibility
     * @param bool $generateCitations generate the references for the citations om cslReferences
     * @param bool $setFiguresTitles use next paragraph as title for figures
     * @param bool $setTablesTitles use next paragraph as title for tables
     * @param bool $replaceTitlesWithReferences for every setTitles, replace the title paragraph with a reference to the figure instead of deleting it
     */
    public function __invoke(
        DOMDocument $dom,
        ?string $citationStyleName,
        ?array $cslReferences,
        bool $generateBibliography,
        bool $generateBibliographyMixedCitations,
        bool $generateCitations,
        bool $setFiguresTitles,
        bool $setTablesTitles,
        bool $replaceTitlesWithReferences
    ){
        $this->logger->debug('Automark options', compact(
            'citationStyleName',
            'generateBibliography',
            'generateBibliographyMixedCitations',
            'generateCitations',
            'setFiguresTitles',
            'setTablesTitles',
            'replaceTitlesWithReferences'
        ));

        

        // Important when doing substring substitutions in the xml!
        $dom->documentElement->normalize();

        $xpath = new DOMXPath($dom);

        // ==== [ Set tables and figures ] ====

        $iRefReplacements = [];

        AbstractCaptionSetter::resetTitlePosition();
        if ($setFiguresTitles) {
            $iRefReplacements += ($this->figuresCaptionSetter)($xpath, $replaceTitlesWithReferences);
        }
        if ($setTablesTitles) {
            $iRefReplacements += ($this->tablesCaptionSetter)($xpath, $replaceTitlesWithReferences);
        }

        // ==== [ Set citations ] ====

        $nodeReplacements = [];
        $refReplacements = [];

        // Bibliography and citations, we need the references and the styleName
        if (! empty($cslReferences) && $citationStyleName) {
            // Clear and rebuild the repository
            $style = $this->citationStyleFactory->get($citationStyleName);
            $this->cslRepository->clear();
            foreach ($cslReferences as $csl) {
                $id = $style->generateId($csl->{CslRepositoryInterface::NOTE});
                $this->cslRepository->add($id, $csl);
            }

            if (! $this->cslRepository->isEmpty()) {
                // Generate bibliography
                if ($generateBibliography) {
                    ($this->bibliographyGenerator)($xpath, $this->cslRepository, $generateBibliographyMixedCitations);
                }
                // Generate citations
                if ($generateCitations) {
                    list($textReplacements, $nodeReplacements) = ($this->citationGenerator)($dom, $xpath, $this->cslRepository, $style);
                    $refReplacements += $textReplacements;
                }
            }
        }

        // ==== [ Dom text and node replacements ] ====

        if (! empty($iRefReplacements)) {
            XmlUtils::iReplaceTextWithNodes($dom, self::SUBS_QUERY, $iRefReplacements);
        }
        if (! empty($refReplacements)) {
            XmlUtils::replaceTextWithNodes($dom, self::SUBS_QUERY, $refReplacements);
        }
        foreach ($nodeReplacements as $key) {
            if ($key->parentNode) {
                $new = $nodeReplacements[$key];
                $key->parentNode->replaceChild($new, $key);
            }
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
