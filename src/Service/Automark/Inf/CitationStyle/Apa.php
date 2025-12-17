<?php

namespace App\Service\Automark\Inf\CitationStyle;

use App\Service\Automark\Dom\CitationStyle\AbstractCitationStyle;
use App\Service\Automark\Dom\CitationStyle\CitationPresetTrait;
use App\Service\Automark\Dom\CitationStyle\CitationStyleInterface;
use App\Service\Automark\Dom\CitationStyle\SupportPresetCitationsInterface;
use App\Service\Automark\Dom\CslRepositoryInterface;
use DOMDocument;
use DOMNode;
use stdClass;

/**
 * Citation Style
 * America Psychological Association (APA)
 */
final class Apa extends AbstractCitationStyle implements SupportPresetCitationsInterface
{
    use CitationPresetTrait;

    const DEBUG = true;

    const NAME                  = 'apa';
    const DISPLAY_NAME          = 'America Psychological Association (APA)';

    // REVIEW character U+00a0 (NBSP) between et al.
    const REGEX_BALANCED_PARENTHESIS = '/\(((?:[^)(]+|(?R))*+)\)/u';
    const REGEX_SINGLE_CITATION_SINGLE_AUTHOR = '/\s*([a-záéíóúñ0-9\s.&\-]+(?:et[  ]+al\.)?)\s*,?\s*(\d{4})(?:,\s*p\.?\s*(\d+))?/ui';
    const REGEX_SINGLE_CITATION_MULTI_AUTHORS = '/\s*([a-záéíóúñ0-9\s.,&\-]+?(?:et[  ]+al\.)?)\s*,?\s*(\d{4})(?:,\s*p\.?\s*(\d+))?/ui';
    const REGEX_SINGLE_CITATION = self::REGEX_SINGLE_CITATION_MULTI_AUTHORS;
    const REGEX_SPLIT_AUTHORS = '/\s*(?:,|(?:\by\b)|(?:\band\b)|&|(?:\bet\b))\s*/iu';

    private int $nextId = 1;

    public function generateId(string $plainreference)
    {
        return $this->nextId++;
    }

    public function buildCitationReferenceMapFromDetected(
        DOMDocument $doc,
        CslRepositoryInterface $repository,
        DOMNode $node,
        &$replacements
    ): void {
        $text = $node->textContent;

        // Find posible citations (anything betwween parenthesis)
        if (! preg_match_all(self::REGEX_BALANCED_PARENTHESIS, $text, $multiCitations)) {
            return;
        }

        $logContext = [
            'style' => static::NAME,
        ];

        foreach ($multiCitations[1] as $group) {
            $citations = explode(';', $group);
            foreach ($citations as $citation) {
                $orgCitation = $logContext['citation'] = @trim($citation);
                if (array_key_exists($orgCitation, $replacements)) {
                    $this->logger->debug('Reference already found', $logContext);
                    continue;
                }
                // Sanetize the string removing rare characters like NBSP to improve search
                $citation = str_replace(' ', ' ', $orgCitation);

                // Check if it is a valid citation
                if (! preg_match(self::REGEX_SINGLE_CITATION, $citation, $citationParts)) {
                    if (static::DEBUG) $this->logger->debug('Not a citation', $logContext);
                    continue;
                }

                // Try to find the reference by preset citation in the repository
                $searchByCitationCriteria = [ [CslRepositoryInterface::CITATIONS => $orgCitation] ];
                if ($citation !== $orgCitation) {
                    $searchByCitationCriteria[] = [CslRepositoryInterface::CITATIONS => $citation];
                }
                if ($list = $repository->query($searchByCitationCriteria, CslRepositoryInterface::OR)) {
                    if (static::DEBUG) $this->logger->debug('Reference found by preset citation', $logContext);
                    $this->addCitationReference($doc, $orgCitation, $list[0], $replacements);
                }
                // Try to find the reference by search criteria in the repository
                elseif ($csl = $this->findReference($repository, $citationParts, $logContext)) {
                    if (static::DEBUG) $this->logger->debug('Reference found by search criteria', $logContext);
                    $this->addCitationReference($doc, $orgCitation, $csl, $replacements);
                } 
                elseif (static::DEBUG) $this->logger->debug('Reference not found', $logContext);
            }
        }
    }

    /**
     * Tries to find the corresponding csl for a given single citation and sets the replacements.
     * @param CslRepositoryInterface $repository reference repository
     * @param array $citation [title/author,year,page]
     * @return ?stdClass
     */
    private function findReference(CSLRepositoryInterface $repository, array $citation, array $logContext = []): ?stdClass
    {
        // Posible patterns to search referenfces by regex ($this->repository->queryPattern)
        // "/$author1,(?: [A-Z].)+, $conjunction $author2,(?: [A-Z].)+ \($year\)/u";
        // "/$author,(?: [A-Z].)+(?:, \w+,(?: [A-Z].)+)+(?:, (?:et[y&]) \w+,(?: [A-Z].)+) \(2020\)/u";

        // REVIEW regex to not capture last space some times
        $title = @trim($citation[1]); // It might by authors
        $year = $citation[2] ?? null;
        $year = @trim($year);
        $page = $citation[3] ?? null;

        // Posible author list
        $authors = preg_split(static::REGEX_SPLIT_AUTHORS, $title);
        $authors = array_values(array_filter(array_map('trim', $authors), fn($a) => $a !== '' && $a !== 'al.'));
        $nauthors = count($authors);
        // REVIEW in case the regex for citations didn't fully work ok
        if ($nauthors == 0) {
            $this->logger->warning('Ignoring unexpected citation', $logContext);
            return null;
        }
        $author = $authors[0];

        $query_authors = [
            CslRepositoryInterface::SEARCHBY_FIRST_AUTHOR      => $author,
            CslRepositoryInterface::SEARCHBY_MULTIPLE_AUTHORS  => $nauthors > 1,
        ];
        // REVIEW use OR to relax the search a bit, it will match if any of the authors is found
        for ($i=1; $i < $nauthors; $i++) {
            // NOTE the AND is not needed and it can be appended to the query_authors array
            $query_authors[CslRepositoryInterface::AND][] = [ CslRepositoryInterface::SEARCHBY_OTHER_AUTHOR => $authors[$i] ];
            //$query_authors[CslRepositoryInterface::OR][] = [ CslRepositoryInterface::SEARCHBY_OTHER_AUTHOR => $authors[$i] ];
        }
        $query = [[
            CslRepositoryInterface::OR => [
                CslRepositoryInterface::SEARCHBY_TITLE => $title,
                CslRepositoryInterface::AND => $query_authors,
            ]
        ]];
        $query = $query_authors;
        if ($year) {
            $query[] = [ CslRepositoryInterface::SEARCHBY_YEAR => $year ];
        }

        $list = $repository->query($query);

        // REVIEW narrow a bit more the search using the page
        // Return the first result of the search
        $n = count($list);
        if ($n > 0) {
            if ($n > 1) {
                $this->logger->warning('Found more than one posible reference', $logContext);
            }
            return current($list);
        }

        // Last resort: BRUTE FORCE, with patterns in case the were not set correctly
        if ($entry = $repository->queryPattern('/^'.preg_quote($title).'/')) {
            return $entry;
        }
        if ($author != $title) {
            if ($entry = $repository->queryPattern('/^'.preg_quote($author).'/')) {
                return $entry;
            }
        }
        return null;
    }
}