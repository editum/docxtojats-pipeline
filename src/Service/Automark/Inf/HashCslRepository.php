<?php

namespace App\Service\Automark\Inf;

use App\Service\Automark\Dom\CslRepositoryInterface;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use stdClass;

final class HashCslRepository implements CslRepositoryInterface, LoggerAwareInterface
{
    const DEBUG = false;

    private LoggerInterface $logger;

    /** @var array */
    private $cslArray;
    /** @var array */
    private $cslMap;

    public function __construct()
    {
        $this->clear();
    }

    public function add($id, stdClass $csl): void
    {
        $firstPage = $lastPage = 0;
        if ($page = $csl->page ?? null) {
            $delimiters = ['-', '–'];
            $page = str_replace($delimiters, $delimiters[0], $page);
            $pages = explode($delimiters[0], $page);
            if (! empty($pages)) {
                $firstPage = current($pages);
                $lastPage = end($pages);
            }
        }

        // Add metadata to the CSL
        $csl->{self::ID} = $id;
        $csl->{self::FIRST_PAGE} = $firstPage;
        $csl->{self::LAST_PAGE} = $lastPage;
        if (property_exists($csl, self::CITATIONS)) {
            if (is_array($csl->{self::CITATIONS})) {
                $csl->{self::CITATIONS} = array_unique($csl->{self::CITATIONS});
            }
            elseif (is_string($csl->{self::CITATIONS})) {
                $csl->{self::CITATIONS} = [$csl->{self::CITATIONS}];
            } 
             else {
                $csl->{self::CITATIONS} = [];
            }
        } else {
            $csl->{self::CITATIONS} = [];
        }

        // Add the object to the collection 
        $this->cslArray[$id] = $csl;
        
        // Map by id
        $this->cslMap[self::SEARCHBY_ID][$id][] = $csl;

        // Map by citation
        foreach ($csl->{self::CITATIONS} as $citation) {
            $this->cslMap[self::CITATIONS][$citation]  = $csl;
        }

        // Map authors
        $countAuthors = 0;
        if (property_exists($csl, 'author'))
        {
            foreach ($csl->author as $author) {
                // Index by fullname, family, ...
                $aliases = (function($author): array {
                    // particle, givem, family
                    $author = array_map('mb_strtolower', (array) $author);
                    extract((array) $author);

                    $aliases = [];
                    $fullname = [];
                    $surname = [];
                    if (!empty($given)) {
                        $fullname[] = $given;
                    }
                    // del, de la, de los...
                    if (!empty($particle)) {
                        $fullname[] = $particle;
                        $surname[] = $particle;
                    }
                    if (!empty($family)) {
                        $fullname[] = $family;
                        $surname[] = $family;
                        $aliases[] = $family;
                        $aliases[] = explode(' ', $family)[0];
                    }
                    $aliases[] = implode(' ', $surname);
                    $aliases[] = implode(' ', $fullname);
                    return array_unique($aliases);
                })($author);

                foreach ($aliases as $alias) {
                    $countAuthors++;
                    if ($countAuthors == 1)
                        $this->cslMap[self::SEARCHBY_FIRST_AUTHOR][$alias][] = $csl;
                    else
                        $this->cslMap[self::SEARCHBY_OTHER_AUTHOR][$alias][] = $csl;
                }
            }
        }
        $this->cslMap[self::SEARCHBY_MULTIPLE_AUTHORS][$countAuthors > 1][] = $csl;

        // Map titles
        if (property_exists($csl, 'title'))
            $this->cslMap[self::SEARCHBY_TITLE][$csl->title][] = $csl;

        // Map year
        if ($issued = $csl->issued ?? null) {
            if (is_string($issued))
                $this->cslMap[self::SEARCHBY_YEAR][$issued][] = $csl;
            else if ($year = $csl->issued->year ?? null)
                $this->cslMap[self::SEARCHBY_YEAR][$year][] = $csl;
        }

        if (self::DEBUG) {
            if (array_key_exists(self::SEARCHBY_FIRST_AUTHOR, $this->cslMap))
                ksort($this->cslMap[self::SEARCHBY_FIRST_AUTHOR]);
            if (array_key_exists(self::SEARCHBY_OTHER_AUTHOR, $this->cslMap))
                ksort($this->cslMap[self::SEARCHBY_OTHER_AUTHOR]);
        }
    }

    public function clear(): void
    {
        $this->cslArray = [];
        $this->cslMap = [ 
            self::CITATIONS                 => [],
            self::SEARCHBY_ID               => [],
            self::SEARCHBY_TITLE            => [],
            self::SEARCHBY_FIRST_AUTHOR     => [],
            self::SEARCHBY_OTHER_AUTHOR     => [],
            self::SEARCHBY_MULTIPLE_AUTHORS => [],
            self::SEARCHBY_YEAR             => [],
        ];
    }

    public function isEmpty(): bool
    {
        return empty($this->cslArray);
    }

    public function getAll(): array
    {
        return $this->cslArray;
    }

    public function getById(int $id): ?stdClass
    {
        return $this->cslArray[$id] ?? null;
    }

    public function query(array $criteria, string $op = self::AND): array
    {
        assert($op == self::AND || $op == self::OR, 'Operation must be AND or OR.');

        $entries = [];
        foreach ($criteria as $key => $value) {
            // Found the leaf that may have the entries
            if (! is_array($value)) {
                // Check for valid key
                if (! array_key_exists($key, $this->cslMap)) {
                    throw new InvalidArgumentException('Search criteria not valid: '.$key);
                }
                // Use lowercase to search by author
                if (in_array($key, [self::SEARCHBY_FIRST_AUTHOR, self::SEARCHBY_OTHER_AUTHOR])) {
                    $value = mb_strtolower($value);
                }
                // Some maps could be a single object like citations map.
                $b = $this->cslMap[$key][$value] ?? [];
                if (is_object($b)) {
                    $b = [$b];
                }
            }
            // We assume it's an operation when $value is an array and $key not numeric
            else if (! is_numeric($key)) {
                $b = $this->query($value, $key);
            }
            // Nested group
            else {
                $b = $this->query($value);
            }

            switch ($op) {
                case self::AND: // Intersection, the loops ends if $entries are empty after the intersection
                    $entries = empty($entries)
                        ? $b
                        : array_uintersect($entries, $b, fn($a, $b) => strcmp(spl_object_hash($a), spl_object_hash($b)))
                    ;
                    if (empty($entries)) return [];
                    break;
                case self::OR: // TODO Merge remove duplicates, problem with object references in array_unique??
                    $entries = array_merge($entries, $b);
                    break;
            }
        }
        return $entries;
    }

    public function queryPattern(string $pattern): ?stdClass
    {
        foreach ($this->cslArray as $entry) {
            if ($note = $entry->{self::NOTE})
            {
                if ($result = @preg_match($pattern, $note))
                    return $entry;
                // Invalid pattern
                else if ($result === false)
                    return null;
            }
        }
        return null;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
