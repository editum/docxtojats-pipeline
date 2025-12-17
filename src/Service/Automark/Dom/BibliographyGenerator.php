<?php

namespace App\Service\Automark\Dom;

use App\Component\Data\PropertyAccess;
use App\Component\Xml\XmlUtils;
use DOMElement;
use DOMXPath;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use stdClass;
use UnexpectedValueException;

final class BibliographyGenerator implements LoggerAwareInterface
{
    const CITATION_REF_ID_PREFIX = 'bib';

    private LoggerInterface $logger;
    private JatsFlavourDetector $jatsFlavourDetector;

    // https://scielo.readthedocs.io/projects/scielo-publishing-schema/pt-br/latest/tagset/elemento-element-citation.html?highlight=element%20citation
    /** @var array Conversion map CSL/AnyStyle -> SPS 1.9 */
    private array $csl2jatsPublicationTypeMap;
    /** @var array Publication titles by type */
    private array $publicationTitleTagMap;

    public function __construct(
        JatsFlavourDetector $jatsFlavourDetector,
        array $csl2jatsPublicationTypeMap,
        array $publicationTitleTagMap
    ){
        $this->logger = new NullLogger();
        $this->jatsFlavourDetector = $jatsFlavourDetector;
        $this->csl2jatsPublicationTypeMap = $csl2jatsPublicationTypeMap;
        $this->publicationTitleTagMap = $publicationTitleTagMap;
    }

    /**
     * Generates the bibliography and appends it to the document.
     *
     * @param DOMXpath $xpath del documento
     * @param CslRepositoryInterface $repository
     * @param bool $addMiexedCitations Add mixed citations to the bibliography (scielo)
     * @throws UnexpectedValueException
     */
    public function __invoke(DOMXPath $xpath, CslRepositoryInterface $repository, bool $addMiexedCitations): void
    {
        // Create parent nodes as needed
        if (! $articleEl = $xpath->query('//article')->item(0))
            throw new UnexpectedValueException("No article node found", 1);
        if (! $backEl = $xpath->query('./back', $articleEl)->item(0))
            $backEl = XmlUtils::appendNewElementTo($articleEl, 'back');
        if (! $listEl = $xpath->query('./ref-list', $backEl)->item(0))
            $listEl = XmlUtils::appendNewElementTo($backEl, 'ref-list');

        $jatsFlavour = $addMiexedCitations ? JatsFlavourDetector::SCIELO : ($this->jatsFlavourDetector)($xpath->document);
        $this->logger->debug('Jats flavour detected: '.$jatsFlavour);

        // Create the ref nodes for each CSL
        foreach ($repository->getAll() as $data) {
            $this->createRefElementFromCSL($listEl, $data, $jatsFlavour);
        }
    }

    /**
     * Creates a ref element from a CSL for the bibliography.
     *
     * @param DOMElement $listEl the parent element
     * @param array|stdClass|PropertyAccess with the CSL
     * @param string $jatsFlavour
     * @return DOMElement the ref element with the bibliography
     */
    private function createRefElementFromCSL(DOMElement $listEl, $cslData, string $jatsFlavour): DOMElement
    {
        $accessorData = $cslData instanceof PropertyAccess ? $cslData : new PropertyAccess($cslData);

        $id = $accessorData->get(CslRepositoryInterface::ID);
        $refEl = XmlUtils::appendNewElementTo($listEl, 'ref', null, ['id' => self::CITATION_REF_ID_PREFIX.$id]);

        $publictionType = $this->normalizePublicationType($accessorData->get('type'));

        // Set Mixed citation
        $note = $accessorData->get(CslRepositoryInterface::NOTE);
        if ($jatsFlavour === JatsFlavourDetector::SCIELO && $note) {
            $mixedCitationEl = XmlUtils::appendNewElementTo($refEl, 'mixed-citation', $note, ['publication-type' => $publictionType]);
        }

        // Create the element citation
        $elementCitationEl = XmlUtils::appendNewElementTo($refEl, 'element-citation', null, ['publication-type' => $publictionType]);

        // Authors and editors
        if ($authors = $accessorData->get('author'))
            $this->extractCSLNames($authors, 'author', $elementCitationEl);
        if ($containerAuthors = $accessorData->get('container-author'))
            $this->extractCSLNames($containerAuthors, 'editor', $elementCitationEl);
        else if ($editor = $accessorData->get('editor'))
            $this->extractCSLNames($editor, 'editor', $elementCitationEl);

        // Title
        if ($title = $accessorData->get('title')) {
            $titleTag = $this->normalizePublictationTitleTag($publictionType);
            $titleEl = XmlUtils::appendNewElementTo($elementCitationEl, $titleTag, $title);
        }

        // Source
        $containerTitle = $accessorData->get('container-title');
        if ($containerTitle && $publictionType !== 'book') {
            $sourceEl = XmlUtils::appendNewElementTo($elementCitationEl, 'source', $containerTitle);
        }

        $publisher = $accessorData->get('publisher');
        if ($publisher) {
            $publisherEl = XmlUtils::appendNewElementTo($elementCitationEl, 'publisher-name', $publisher);
        }

        $publisherPlace = $accessorData->get('publisher-place');
        if ($publisherPlace) {
            $publisherLocEl = XmlUtils::appendNewElementTo($elementCitationEl, 'publisher-loc', $publisherPlace);
        }

        $volume = $accessorData->get('volume');
        if ($volume) {
            $volumeEl = XmlUtils::appendNewElementTo($elementCitationEl, 'volume', $volume);
        }

        $issue = $accessorData->get('issue');
        if ($issue) {
            $issueEl = XmlUtils::appendNewElementTo($elementCitationEl, 'issue', $issue);
        }

        $event = $accessorData->get('event');
        if ($event) {
            $confTitleEl = XmlUtils::appendNewElementTo($elementCitationEl, 'conf-name', $event);
        }

        $event = $accessorData->get('event-place');
        if ($event && $publictionType === 'conference') { // Zotero adds event-place to books and chapters
            $confLocEl = XmlUtils::appendNewElementTo($elementCitationEl, 'conf-loc', $event);
        }

        // Pages
        $page = $accessorData->get('page');
        if ($page) {
            $pageRangeEl = XmlUtils::appendNewElementTo($elementCitationEl, 'page-range', $page);
        }

        $pageFirst = $accessorData->get('page-first');
        if ($pageFirst) {
            $pageFirstEl = XmlUtils::appendNewElementTo($elementCitationEl, 'fpage', $pageFirst);
        }

        // Identificators
        $doi = $accessorData->get('DOI');
        if ($doi) {
            $doiEl = XmlUtils::appendNewElementTo($elementCitationEl, 'pub-id', $doi, ['pub-id-type' => 'doi']);
        }

        $pmid = $accessorData->get('PMID');
        if ($pmid) {
            $pmidEl = XmlUtils::appendNewElementTo($elementCitationEl, 'pub-id', $doi, ['pub-id-type' => 'pmid']);
        }

        $url = $accessorData->get('URL');
        if ($url) {
            $this->createExtLink($elementCitationEl, $url, null, $jatsFlavour);
        }

        $issn = $accessorData->get('ISSN');
        if ($issn) {
            $issnEl = XmlUtils::appendNewElementTo($elementCitationEl, 'issn', $issn);
        }

        // Date
        $issued = $accessorData->get('issued');
        if ($issued) {
            if ($dateParts = PropertyAccess::getValue($issued, 'date-parts')) {
                if (array_key_exists(0, $dateParts[0])) {
                    $yearEl = XmlUtils::appendNewElementTo($elementCitationEl, 'year', $dateParts[0][0]);
                }

                if (array_key_exists(1, $dateParts[0])) {
                    $monthEl = XmlUtils::appendNewElementTo($elementCitationEl, 'month', $dateParts[0][1]);
                }

                if (array_key_exists(2, $dateParts[0])) {
                    $dayEl = XmlUtils::appendNewElementTo($elementCitationEl, 'day', $dateParts[0][2]);
                }
            } else {
                $rawDate = null;
                // The date may come in the property raw
                if (! is_string($issued)) {
                    $rawDate = PropertyAccess::getValue($issued, 'raw');
                // The date may be a string
                } else if (! preg_match('/^\d{4}$/', $issued)) {
                    $rawDate = $issued;
                // The date may be a string only with the year
                } else if ($year = date('Y', strtotime("$issued/01/01"))) {
                    XmlUtils::appendNewElementTo($elementCitationEl, 'year', $year);
                }
                if ($rawDate) {
                    if ($formattedDate = strtotime($rawDate)) {
                        if ($year = date('Y', $formattedDate)) {
                            $yearEl = XmlUtils::appendNewElementTo($elementCitationEl, 'year', $year);
                        }
                        if ($month = date('m', $formattedDate)) {
                            $monthEl = XmlUtils::appendNewElementTo($elementCitationEl, 'month', $month);
                        }
                        if ($day = date('d', $formattedDate)) {
                            $dayEl = XmlUtils::appendNewElementTo($elementCitationEl, 'day', $day);
                        }
                    }
                }
            }
        }

        return $refEl;
    }

    /**
     * Creates a ext-link with @ext-link-type and @xlink:href.
     * If the type is a DOI, PMID, PMC, etc the param text will be ignored.
     *
     * SPS 1.9 only supports: uri|clinical-trial.
     *
     * @param DOMElement $parentEl where it will be appended
     * @param string $href
     * @param ?string $text overwrite the node text
     * @param string $flavour jats flavour
     * @return DOMElement the ext-link created
     */
    private function createExtLink(DOMElement $parentEl, string $href, ?string $text = null, string $flavour = JatsFlavourDetector::JATS): DOMElement
    {
        $linkType = 'uri'; // default

        // Use de href as text
        if ($text) {
            $text = $href;
        }

        // DOI detection
        if (preg_match('/^(https?:\/\/(?:dx\.)?doi\.org\/)?(10\.\d{4,9}\/\S+)$/i', $href, $m)) {
            $linkType = 'doi';
            $doi = $m[2];
            $href = $m[1] ? $m[0] : 'https://doi.org/' . $doi;
            $text = $doi;
        }
        // PMC detection
        elseif (preg_match('/https?:\/\/www\.ncbi\.nlm\.nih\.gov\/pmc\/articles\/PMC(\d{7,9})\/?/i', $href, $m)) {
            $linkType = 'pmc';
            $text = 'PMC' . $m[1];
        }
        // PMID detection
        elseif (preg_match('/(https?:\/\/pubmed\.ncbi\.nlm\.nih.gov\/)?(\d{5,9})\/?/i', $href, $m)) {
            $linkType = 'pmid';
            $pmid = $m[2];
            $href = $m[1] ? $m[0] : 'https://pubmed.ncbi.nlm.nih.gov/' . $pmid;
            $text = $pmid;
        }
        // GenBank detection
        elseif (preg_match('/https?:\/\/(?:www\.)?ncbi\.nlm\.nih\.gov\/nuccore\/([A-Z0-9_.-]+)/i', $href, $m)) {
            $linkType = 'genbank';
            $text = $m[1];
        }
        // Clinical trial detection
        elseif (preg_match('/^(?:https?:\/\/(?:www\.)?clinicaltrials\.gov\/(?:ct2\/)?show\/)?(NCT\d{8,9})$/i', $href, $m)) {
            $linkType = 'clinical-trial';
            $trialId = $m[1];
            $href = 'https://clinicaltrials.gov/show/' . $trialId;
            $text = $trialId;
        }
        // E-mail detection
        elseif (preg_match('/^mailto:([^@]+@[^@]+\.[^@]+)$/i', $href, $m) || filter_var($href, FILTER_VALIDATE_EMAIL)) {
            $linkType = 'email';
            $email = $m[1] ?? $href;
            $href = (stripos($href, 'mailto:') === 0) ? $href : 'mailto:' . $email;
            $text = $email;
        }
        // FTP detection
        elseif (preg_match('/^ftp:\/\/\S+/i', $href)) {
            $linkType = 'ftp';
            // keep text as-is unless redundant
            $text = ($text === $href) ? basename($href) : $text;
        }

        // SciELO SPS flavor: Only 'uri' and 'clinical-trial' are accepted
        if ($flavour === JatsFlavourDetector::SCIELO) {
            if ($linkType !== 'clinical-trial') {
                $linkType = 'uri';
            }
        }

        // Create and append <ext-link> node
        $extLink = XmlUtils::appendNewElementTo($parentEl, 'ext-link', $text, [
            'ext-link-type' => $linkType,
            'xlink:href'    => $href,
        ]);

        return $extLink;
    }

    /**
     * Extracts authors names fromt the CSL and adds them to the citations.
     *
     * @param array $authors Author list.
     * @param string $personGroupType (e.g. "author", "editor").
     * @param \DOMElement $elementCitationEl Element <element-citation> where authors are added.
     */
    private function extractCSLNames(array $authors, string $personGroupType, \DOMElement $elementCitationEl): void
    {
        $personGroupEl = XmlUtils::appendNewElementTo($elementCitationEl, 'person-group', null, [
            'person-group-type' => $personGroupType,
        ]);

        foreach ($authors as $author) {
            $accessorAuthor = new PropertyAccess($author);

            $family = $accessorAuthor->get('family');
            $given = $accessorAuthor->get('given');

            if ($family || $given) {
                $nameEl = XmlUtils::appendNewElementTo($personGroupEl, 'name');
                if ($family) {
                    XmlUtils::appendNewElementTo($nameEl, 'surname', $family);
                }
                if ($given) {
                    XmlUtils::appendNewElementTo($nameEl, 'given-names', $given);
                }
            }
        }
    }

    /**
     * Normalizes de publication type.
     *
     * @param ?string $cslPubType the csl publication title
     * @return string the corresponding value or other for fallback
     */
    private function normalizePublicationType(?string $cslPubType): string
    {
        return !$cslPubType || !isset($this->csl2jatsPublicationTypeMap[$cslPubType]) ? 'other' : $this->csl2jatsPublicationTypeMap[$cslPubType];
    }

    /**
     * Normalizes de publication title tag-name.
     *
     * @param ?string the publication type.
     * @return string the prefered tag name for the title.
     */
    protected function normalizePublictationTitleTag(?string $publicationType): string
    {
        return !$publicationType || !isset($this->publicationTitleTagMap[$publicationType]) ? 'article-title' : $this->publicationTitleTagMap[$publicationType];
    }


    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
