<?php

namespace App\Service\Automark\Dom;

use stdClass;

interface CslRepositoryInterface
{
    // Extra metadata to be added to CSL
    const ID                        = '_id';        // Reference id
    const CITATIONS                 = '_citations'; // Some styles my write citations found here
    const NOTE                      = 'note';       // Mixed citation (scielo)
    const FIRST_PAGE                = '_firstPage'; // First page if any
    const LAST_PAGE                 = '_lastPage';  // Last page if any

    // Constants for queries
    const AND                       = '$and';
    const OR                        = '$or';
    const SEARCHBY_ID               = 'id';
    const SEARCHBY_TITLE            = 'title';
    const SEARCHBY_YEAR             = 'issued\year';
    const SEARCHBY_FIRST_AUTHOR     = 'author\first';
    const SEARCHBY_OTHER_AUTHOR     = 'author\other';
    const SEARCHBY_MULTIPLE_AUTHORS = 'author\multiple';

    /**
     * Adds an element to the repository.
     * @param mixed $id element id
     * @param stdClas $csl the citation style language object
     */
    public function add($id, stdClass $csl): void;

    /**
     * Clears all the data in the repository.
     */
    public function clear(): void;

    /**
     * Returns array of csl's.
     * @return stdClass[];
     */
    public function getAll(): array;

    /**
     * Return the CSL object with the required id.
     * @param int $id id of the object
     * @return ?stdClass the CSL object
     */
    public function getById(int $id): ?stdClass;

    /**
     * True if the repository is empty
     * @return bool
     */
    public function isEmpty(): bool;
    
    /**
     * Queries the CSL collection following a criteria.
     * It supports operators like '$and' and '$or', and the criterias can be nested.
     * Example:
     *  // Search by year 1989 and authors Batman or Robin.
     *  $query = [
     *    [
     *      '$or' => [
     *        self::SEARCHBY_FIRST_AUTHOR => 'Batman',
     *        self::SEARCHBY_FIRST_AUTHOR => 'Robin',
     *      ]
     *    ],
     *    self::SEARCHBY_YEAR => '1989'
     *  ];
     * @param array $criteria as the example above
     * @param string $op operation by default '$and'
     * @return stdClass[] array of CSLs
     */
    public function query(array $criteria, string $op = self::AND): array;

    /**
     * Queries the CSL collection using a regex expresion over the note properties wich contains the plain reference.
     * @param string $pattern the regex expresion
     * @return ?stdClass with the first match found
     */
    public function queryPattern(string $pattern): ?stdClass;
}
