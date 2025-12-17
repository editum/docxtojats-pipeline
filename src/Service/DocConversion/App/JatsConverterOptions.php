<?php

namespace App\Service\DocConversion\App;

use App\Service\Automark\App\CslGenerator;
use InvalidArgumentException;
use App\Service\Automark\Dom\CitationStyle\CitationStyleFactory;
use DOMDocument;
use DOMNode;
use stdClass;
use Symfony\Component\Mime\MimeTypes;

/**
 * This class is used to define, setup and set all options supported be the JATS converter command
 * and controller.
 *
 * Every option has his own:
 *  - Description
 *  - Invalid Argument message
 *  - shortcut
 *  - default value
 *  - current value
 *  - set function
 */
final class JatsConverterOptions
{
    const ARCHIVE                           = 'archive';
    const NORMALIZE                         = 'normalize';
    const AUTOMARK                          = 'automark';
    const FRONT_XML_FILE                    = 'front-file';
    const BIBLIOGRAPHY_FILE                 = 'bibliography-file';
    const CITATION_STYLE                    = 'citation-style';
    const SET_BIBLIOGRAPHY_MIXED_CITATIONS  = 'set-bibliography-mixed-citations';
    const SET_FIGURE_TITLES                 = 'set-figures-titles';
    const SET_TABLE_TITLES                  = 'set-tables-titles';
    const REPLACE_TITLES_WITH_REFERENCES    = 'replace-titles-with-references';
    const REMOVE_SECTIONMS                  = 'remove-sections';

    const K_DESCRIPTION                     = 'description';
    const K_INVALID_ARGUMENT                = 'invalidArgument';
    const K_SHORTCUT                        = 'shortcut';
    const K_DEFAULT                         = 'default';
    const K_VALUE                           = 'value';
    const K_SET                             = 'set';

    private CitationStyleFactory $citationStyleFactory;
    private CslGenerator $cslGenerator;

    private array $options;

    // JatsConverter options
    public bool $archive                        = false;
    public bool $normalize                      = false;
    public ?DOMNode $front                      = null;
    public ?string $frontFile                   = null;
    public array $removeSectionsIds             = [];

    // Automark citations options
    public ?array $csl                          = null;
    public ?string $citationStyle               = null;
    public ?string $bibliographyFile            = null;
    public ?string $txtBibliographyFile         = null;
    public bool $generateScieloMixedCitations   = false;

    // Automark title options
    public bool $setFiguresTitles               = false;
    public bool $setTablesTitles                = false;
    public bool $replaceTitlesWithReferences    = false;

    public function __construct(
        CitationStyleFactory $citationStyleFactory,
        CslGenerator $cslGenerator
    ){
        $this->cslGenerator = $cslGenerator;
        $this->citationStyleFactory = $citationStyleFactory;
        $this->options = $this->initializeOptions();
    }

    /**
     * Get individual or all options with their description and defalut values.
     * @param ?string $option option name or null for all
     * @return array
     */
    public function get(?string $option = null): array
    {
        if (null === $option) {
            return $this->options;
        }
        if (! array_key_exists($option, $this->options)) {
            throw new InvalidArgumentException('Invalid option '.$option);
        }
        return $this->options[$option];
    }

    /**
     * Get the description for an option.
     * @param string $option
     * @return string the description
     */
    public function getDescription(string $option): ?string
    {
        return $this->get($option)[self::K_DESCRIPTION];
    }

    /**
     * Get the message when an invalid argument is used with an option.
     * If no message is found it will return
     *  Invalid value for "$option"
     * @param string $option
     * @return string the message
     */
    public function getInvalidArgumentMessage(string $option): string
    {
        return $this->get($option)[self::K_INVALID_ARGUMENT] ?? 'Invalid value for '.$option;
    }

    /**
     * Get supported options.
     * @return array
     */
    public function getNames(): array
    {
        return array_keys($this->options);
    }

    /**
     * Get the value of an option
     * @param string $option option name
     * @return mixed
     */
    public function getValue(string $option)
    {
        return $this->get($option)[self::K_VALUE];
    }

    /**
     * Sets the option to a value.
     * @param string $option the name of the option
     * @return self
     */
    public function set(string $option, $value = true): self
    {
        $this->get($option)[self::K_SET]($value);
        return $this;
    }

    /**
     * Sets the option to its default value.
     * @param string $option the name of the option
     * @return self
     */
    public function unset(string $option): self
    {
        $option = $this->get($option);
        $option[self::K_SET]($option[self::K_DEFAULT]);
        return $this;
    }

    private function checkFileExists($value) {
        if (! file_exists($value)) {
            throw new InvalidArgumentException("File not found: ". $value);
        }
        if (! is_readable($value)) {
            throw new InvalidArgumentException("File not readable: ". $value);
        }
    }

    private function initializeOptions(): array
    {
        assert(isset($this->citationStyleFactory), 'citationStyleFactory must be initialized');
        assert(isset($this->cslGenerator), 'cslGenerator must be initialized');

        return [
            self::ARCHIVE => [
                self::K_DESCRIPTION => 'Create the output as an archive.',
                self::K_SHORTCUT    => 'z',
                self::K_DEFAULT     => $this->archive,
                self::K_VALUE       => &$this->archive,
                self::K_SET         => function($value): void {
                    if (! is_null($value) && ! is_bool($value)) {
                        throw new InvalidArgumentException($this->getInvalidArgumentMessage(self::ARCHIVE));
                    }
                    $this->archive = $value ?? false;
                },
            ],
            self::NORMALIZE => [
                self::K_DESCRIPTION => 'Optimize the document before converting to JATS.',
                self::K_SHORTCUT    => null,
                self::K_DEFAULT     => $this->normalize,
                self::K_VALUE       => &$this->normalize,
                self::K_SET         => function($value): void {
                    if (! is_null($value) && ! is_bool($value)) {
                        throw new InvalidArgumentException($this->getInvalidArgumentMessage(self::NORMALIZE));
                    }
                    $this->normalize = $value ?? false;
                },
            ],
            self::REMOVE_SECTIONMS => [
                self::K_DESCRIPTION => 'Remove sections from the JATS output.',
                self::K_SHORTCUT    => 'r',
                self::K_DEFAULT     => $this->removeSectionsIds,
                self::K_VALUE       => &$this->removeSectionsIds,
                self::K_SET         => function($value): void {
                    if (is_null($value) || empty($value)) {
                        $value = [];
                    }
                    elseif (is_string($value)) {
                        $value = explode(' ', $value);
                    }
                    if (! is_array($value)) {
                        throw new InvalidArgumentException($this->getInvalidArgumentMessage(self::REMOVE_SECTIONMS));
                    }
                    $this->removeSectionsIds = $value;
                },
            ],
            self::FRONT_XML_FILE => [
                self::K_DESCRIPTION         => 'XML file with front metadata to insert into the JATS output.',
                self::K_INVALID_ARGUMENT    => self::FRONT_XML_FILE.' only accepts an ".xml" files',
                self::K_SHORTCUT            => 'f',
                self::K_DEFAULT             => $this->frontFile,
                self::K_VALUE               => &$this->frontFile,
                self::K_SET                 => function($value): void {
                    if (is_null($value) || empty($value)) {
                        $this->frontFile = $this->front = null;
                        return;
                    }
                    if (! is_string($value)) {
                        throw new InvalidArgumentException($this->getInvalidArgumentMessage(self::FRONT_XML_FILE));
                    }
                    $this->checkFileExists($value);
                    // Load front data
                    $frontDoc = new DOMDocument();
                    $frontDoc->load($value);
                    $this->front = $frontDoc->getElementsByTagName('front')->item(0);
                    $this->frontFile = $value;
                },
            ],
            self::AUTOMARK => [
                self::K_DESCRIPTION => 'Set all AutoMark 2000 (TM) options to true, the value is the citation style to use. Valid values: ['.implode('|', $this->citationStyleFactory->getNames()).'].',
                self::K_SHORTCUT    => null,
                self::K_DEFAULT     => null,
                self::K_VALUE       => null,
                self::K_SET         => function($value) {
                    if (is_null($value) || empty($value)) {
                        return;
                    }
                    if (! $this->citationStyleFactory->isValid($value)) {
                        throw new InvalidArgumentException($this->getInvalidArgumentMessage(self::AUTOMARK));
                    }
                    // TODO check mimetype
                    $this->setFiguresTitles = true;
                    $this->setTablesTitles = true;
                    $this->replaceTitlesWithReferences = true;
                    $this->generateScieloMixedCitations = true;
                    $this->citationStyle = $value;
                }
            ],
            self::BIBLIOGRAPHY_FILE => [
                // TODO: anystyle doesn't work with bib, it should be converter with pandoc or something to ref
                self::K_DESCRIPTION         => 'Accepts CSL with "notes" field (.json), Plain Text with a reference in each line (.ref), or an uncleaned Plain Text (.txt); CSL is preferred and ref over txt. Must be used together with --'.self::CITATION_STYLE.'.',
                self::K_INVALID_ARGUMENT    => self::BIBLIOGRAPHY_FILE.' only accepts ".json", ".ref" or ".txt" files',
                self::K_SHORTCUT            => 'b',
                self::K_DEFAULT             => $this->bibliographyFile,
                self::K_VALUE               => &$this->bibliographyFile,
                self::K_SET                 => function(?string $value): void {
                    if (is_null($value) || empty($value)) {
                        $this->bibliographyFile = $this->csl = null;
                        return;
                    }
                    $this->bibliographyFile = $value;

                    // Get the extensión and mimetypes
                    $this->checkFileExists($value);
                    $ext = pathinfo($value)['extension'] ?? 'noext';
                    $mimeTypes = MimeTypes::getDefault()->getMimeTypes($ext);

                    // Asume the json is in CSL format and from us (with "note" field for mixed-citations)
                    if (in_array('application/json', $mimeTypes, true)) {
                        if (! $csl = json_decode(file_get_contents($value))) {
                            throw new InvalidArgumentException('Error decoding: '.$value);
                        }
                        // REVIEW: Wizard marked_data.json compatibility: the csl is in the property csl
                        $this->csl = $csl instanceof stdClass && property_exists($csl, 'csl') ? $csl->csl : $csl;
                    }
                    // Needs to be converted
                    elseif (!empty(array_intersect(['text/plain'], $mimeTypes)) || $ext === 'ref') {
                        $this->csl = ($this->cslGenerator)($value);
                    }
                    // Not supported
                    else {
                        throw new InvalidArgumentException($this->getInvalidArgumentMessage(self::BIBLIOGRAPHY_FILE));
                    }
                },
            ],
            self::CITATION_STYLE => [
                self::K_DESCRIPTION => 'Specifies the citation style used in the document. When provided, AutoMark 2000™ attempts to identify and mark both the bibliography and in-text citations. Accepted values: ['.implode('|', $this->citationStyleFactory->getNames()).'].',
                self::K_SHORTCUT    => 's',
                self::K_DEFAULT     => $this->citationStyle,
                self::K_VALUE       => &$this->citationStyle,
                self::K_SET         => function($value): void {
                    if (! is_null($value) && ! empty($value) && ! $this->citationStyleFactory->isValid($value)) {
                        throw new InvalidArgumentException($this->getInvalidArgumentMessage(self::CITATION_STYLE));
                    }
                    $this->citationStyle = $value;
                },
            ],
            self::SET_BIBLIOGRAPHY_MIXED_CITATIONS => [
                self::K_DESCRIPTION => 'By default, mixed citations are added when the output is SciELO-compatible. This option forces their inclusion. Must be used together with --'.self::CITATION_STYLE.'.',
                self::K_SHORTCUT    => null,
                self::K_DEFAULT     => $this->generateScieloMixedCitations,
                self::K_VALUE       => &$this->generateScieloMixedCitations,
                self::K_SET         => function($value): void {
                    if (! is_null($value) && ! is_bool($value)) {
                        throw new InvalidArgumentException($this->getInvalidArgumentMessage(self::SET_BIBLIOGRAPHY_MIXED_CITATIONS));
                    }
                    $this->generateScieloMixedCitations = $value ?? false;
                },
            ],
            self::SET_FIGURE_TITLES => [
                self::K_DESCRIPTION => 'Locate paragraphs adjacent to a figure (either before or after) that match the pattern “Figure x: name” and use them as the figure’s title.',
                self::K_SHORTCUT    => null,
                self::K_DEFAULT     => $this->setFiguresTitles,
                self::K_VALUE       => &$this->setFiguresTitles,
                self::K_SET         => function($value): void {
                    if (! is_null($value) && ! is_bool($value)) {
                        throw new InvalidArgumentException($this->getInvalidArgumentMessage(self::SET_FIGURE_TITLES));
                    }
                    $this->setFiguresTitles = $value ?? false;
                },
            ],
            self::SET_TABLE_TITLES => [
                self::K_DESCRIPTION => 'Locate paragraphs adjacent to a table (either before or after) that match the pattern “Table x: name” and use them as the table’s title.',
                self::K_SHORTCUT    => null,
                self::K_DEFAULT     => $this->setTablesTitles,
                self::K_VALUE       => &$this->setTablesTitles,
                self::K_SET         => function($value): void {
                    if (! is_null($value) && ! is_bool($value)) {
                        throw new InvalidArgumentException($this->getInvalidArgumentMessage(self::SET_TABLE_TITLES));
                    }
                    $this->setTablesTitles = $value ?? false;
                },
            ],
            self::REPLACE_TITLES_WITH_REFERENCES => [
                self::K_DESCRIPTION => 'Replace figure or table titles paragraphs that are detected with their corresponding reference. Used in conjunction with --'.self::SET_TABLE_TITLES.' and --'.self::SET_FIGURE_TITLES.'.',
                self::K_SHORTCUT    => null,
                self::K_DEFAULT     => $this->replaceTitlesWithReferences,
                self::K_VALUE       => &$this->replaceTitlesWithReferences,
                self::K_SET         => function($value): void {
                    if (! is_null($value) && ! is_bool($value)) {
                        throw new InvalidArgumentException($this->getInvalidArgumentMessage(self::REPLACE_TITLES_WITH_REFERENCES));
                    }
                    $this->replaceTitlesWithReferences = $value ?? false;
                },
            ],
        ];
    }
}
