<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Config;

use Dpi\NamespaceShortener\Core\Exception\InvalidImportRoot;
use Dpi\NamespaceShortener\Core\Php\ReservedNames;

use function Safe\preg_match;

/**
 * An exact, aliased, or wildcard root. See docs/guide/configuration.md.
 */
final readonly class ImportRoot
{
    private const string SEGMENT = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*';

    /**
     * @param non-empty-list<string> $segments the literal segments, without any wildcard
     */
    private function __construct(
        public array $segments,
        public bool $wildcard,
        public ?string $alias,
    ) {
    }

    /**
     * @throws InvalidImportRoot
     */
    public static function fromString(string $definition): self
    {
        $segment = self::SEGMENT;
        $pattern = '/^\s*\\\\?(?<name>' . $segment . '(?:\\\\' . $segment . ')*)(?<wildcard>\\\\\*)?(?:\s+[aA][sS]\s+(?<alias>' . $segment . '))?\s*$/D';

        preg_match($pattern, $definition, $matches, \PREG_UNMATCHED_AS_NULL);

        if ([] === $matches) {
            throw new InvalidImportRoot(\sprintf('Import root "%s" is malformed: expected "A\B\C", "A\B\C as D", or "A\B\C\*".', $definition));
        }

        $segments = explode('\\', $matches['name']);
        $wildcard = null !== $matches['wildcard'];
        $alias = $matches['alias'];

        if ($wildcard && null !== $alias) {
            throw new InvalidImportRoot(\sprintf('Import root "%s" cannot have an alias: a wildcard root imports a different name for each match.', $definition));
        }

        if (null !== $alias && (ReservedNames::isKeyword($alias) || ReservedNames::isReservedClassName($alias))) {
            throw new InvalidImportRoot(\sprintf('Import root "%s" cannot use "%s" as an alias: PHP reserves that name.', $definition, $alias));
        }

        if (!$wildcard && null === $alias && ReservedNames::isReservedClassName($segments[array_key_last($segments)])) {
            throw new InvalidImportRoot(\sprintf('Import root "%s" cannot be imported as "%s": PHP reserves that name. Give it an alias.', $definition, $segments[array_key_last($segments)]));
        }

        return new self($segments, $wildcard, $alias);
    }

    /**
     * @param non-empty-list<string> $name
     */
    public function match(array $name): ?RootMatch
    {
        $prefixLength = \count($this->segments);
        $rootLength = $this->wildcard ? $prefixLength + 1 : $prefixLength;

        if (\count($name) < $rootLength) {
            return null;
        }

        foreach ($this->segments as $index => $segment) {
            if (0 !== strcasecmp($segment, $name[$index])) {
                return null;
            }
        }

        /** @var non-empty-list<string> $root */
        $root = $this->wildcard
            ? [...$this->segments, $name[$prefixLength]]
            : $this->segments;

        return new RootMatch(
            $root,
            $this->alias ?? $root[array_key_last($root)],
            null !== $this->alias,
            \array_slice($name, $rootLength),
        );
    }

    /**
     * Whether both produce the same imports. Case counts, because imports use the case configured.
     */
    public function isSameAs(self $other): bool
    {
        return $this->segments === $other->segments && $this->wildcard === $other->wildcard && $this->alias === $other->alias;
    }

    /**
     * Identifies the namespace this rule covers, whatever its alias.
     */
    public function key(): string
    {
        return strtolower(implode('\\', $this->segments)) . ($this->wildcard ? '\\*' : '');
    }
}
