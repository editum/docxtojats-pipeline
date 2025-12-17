<?php

namespace App\Service\Automark\Inf;

use Symfony\Component\Yaml\Yaml;

/**
 * Factory class to load configuration files for automark.
 * - Loads keywords from different languages and merge theme.
 * - Loads lookup tables.
 */
final class LookupFactory
{
    private string $keywordsDir;
    private string $lookupTablesDir;
    
    private array $keywordsCategories = [];
    private array $lookupTable = [];

    public function __construct(string $keywordsDir, string $lookupTablesDir)
    {
        $this->keywordsDir = $keywordsDir;
        $this->lookupTablesDir = $lookupTablesDir;
    }

    // === [Generic access methods] ===

    /**
     * Rerturns an array of keywords.
     *
     * @param string $category
     * @return array
     */
    public function getKeywords(string $category): array
    {
        $this->loadLocalizedKeywords();
        return $this->keywordsCategories[$category] ?? [];
    }

    /**
     * Rerturns a lookupTable.
     *
     * @param string $category
     * @return array
     */
    public function getLookupTable(string $category): array
    {
        $this->loadLookupTables();
        return $this->lookupTable[$category] ?? [];
    }


    // === [Keyword specific categories] ===

    /**
     * Returns an array with all the keywords used to search the bibliography section.
     *
     * @return array<string>
     */
    public function getBibliographyKeywords(): array
    {
        return $this->getKeywords('bibliography');
    }

    /**
     * Returns an array with all the posible keywords used to detect figure titles.
     *
     * @return array<string>
     */
    public function getFigureKeywords(): array
    {
        return $this->getKeywords('figure');
    }

    /**
     * Returns an array with all the posible keywords used to detect table titles.
     *
     * @return array<string>
     */
    public function getTableKeywords(): array
    {
        return $this->getKeywords('table');
    }


    // === [Keyword specific categories] ===

    /**
     * Returns an associative array mapping CSL publication types to JATS publication types.
     *
     * @return array<string, string>
     */
    public function getCsl2JatsPublicationTypeMap(): array
    {
        return $this->getLookupTable('csl2jats_publication_type_map');
    }

    /**
     * Returns an associative array mapping JATS publication types to their corresponding title tags.
     *
     * @return array<string, string>
     */
    public function getPublicationTitleTagMap(): array
    {
        return $this->getLookupTable('publication_title_tag_map');
    }


    // === [Load methods] ===

    /**
     * Loads all YAML files for the different categories and merges all languages
     * into a single associative array.
     */
    private function loadLocalizedKeywords(): void
    {
        if (!empty($this->keywordsCategories)) {
            return;
        }

        $files = glob($this->keywordsDir.DIRECTORY_SEPARATOR.'*.yaml');
        $all = [];

        // Get all languages from all categories and merge them
        foreach ($files as $file) {
            $yaml = Yaml::parseFile($file);
            if (!isset($yaml['keywords']) || !is_array($yaml['keywords'])) {
                continue;
            }
            foreach ($yaml['keywords'] as $category => $keywords) {
                $all[$category] = array_merge(
                    $all[$category] ?? [],
                    array_map(function($kw){
                        return mb_strtolower($kw);
                }, $keywords));
            }
        }

        // Cleanup
        foreach ($all as $category => $keywords) {
            $this->keywordsCategories[$category] = array_values(array_unique($keywords));
            sort($this->keywordsCategories[$category]);
        }
    }

    /**
     * Loads all config files with lookuptables.
     * The keys from previous files will be preserved.
     */
    private function loadLookupTables(): void
    {
        if (!empty($this->lookupTable)) {
            return;
        }

        $files = glob($this->lookupTablesDir.DIRECTORY_SEPARATOR.'*.yaml');
        foreach ($files as $file) {
            $yaml = Yaml::parseFile($file);
            if (!isset($yaml['mappings']) || !is_array(['mappings'])) {
                continue;
            }
            $this->lookupTable += $yaml['mappings'];
        }
    }
}
