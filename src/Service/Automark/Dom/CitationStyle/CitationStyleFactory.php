<?php

namespace App\Service\Automark\Dom\CitationStyle;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CitationStyleFactory
{
    /** @var CitationStyleInterface[] Style locator array */
    private array $styleLocator;

    private LoggerInterface $logger;

    public function __construct(iterable $styleLocator, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->styleLocator = $styleLocator instanceof \Traversable ? iterator_to_array($styleLocator) : $styleLocator;
    }

    /**
     * Returns a new instance of a citation style
     * @param string $name the name of the citation style
     * @return CitationStyleInterface new instance of the style
     * @throws InvalidArgumentException
     */
    public function get(string $name): CitationStyleInterface
    {
        $name = strtolower($name);
        if (! isset($this->styleLocator[$name])) {
            throw new InvalidArgumentException("Style not found: {$name}");
        }
        $instance = $this->styleLocator[$name]::create();
        $instance->setLogger($this->logger);
        return $instance;
    }

    /**
     * Returns a list of all avaliable styles.
     * @return array
     */
    public function getNames(bool $displayNames = false): array
    {
        if (! $displayNames) {
            return array_keys($this->styleLocator);
        }
        $ret = [];
        foreach ($this->styleLocator as $style) {
            $ret[$style->displayName()] = $style->name();
        }
        return $ret;
    }

    /**
     * Returns true if the style is valid
     * @param string $name
     * @return bool
     */
    public function isValid(string $name): bool
    {
        return isset($this->styleLocator[strtolower($name)]);
    }
}
