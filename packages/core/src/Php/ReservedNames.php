<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Php;

/**
 * Names PHP or phpDoc give a meaning of their own.
 */
final class ReservedNames
{
    /**
     * Fatal as the local name of an import: "is a special class name".
     */
    public const array CLASS_NAMES = [
        'array', 'bool', 'callable', 'false', 'float', 'int', 'iterable', 'mixed', 'never', 'null', 'object', 'parent', 'self', 'static',
        'string', 'true', 'void',
    ];

    /**
     * Unusable as an alias, or as a name standing alone rather than as part of a qualified name.
     */
    public const array KEYWORDS = [
        '__class__', '__dir__', '__file__', '__function__', '__line__', '__method__', '__namespace__', '__property__',
        '__trait__', 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const',
        'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor',
        'endforeach', 'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final', 'finally', 'fn', 'for',
        'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once', 'instanceof',
        'insteadof', 'interface', 'isset', 'list', 'match', 'namespace', 'new', 'or', 'print', 'private', 'protected',
        'public', 'readonly', 'require', 'require_once', 'return', 'switch', 'throw', 'trait', 'try', 'unset', 'use',
        'var', 'while', 'xor', 'yield',
    ];

    /**
     * Type names in phpDoc that are not references to classes.
     */
    public const array DOC_PSEUDO_TYPES = [
        'array', 'boolean', 'callable', 'callback', 'double', 'integer', 'list', 'max', 'min', 'number', 'numeric',
        'resource', 'scalar', '$this',
    ];

    public static function isReservedClassName(string $name): bool
    {
        return \in_array(strtolower($name), self::CLASS_NAMES, true);
    }

    public static function isKeyword(string $name): bool
    {
        return \in_array(strtolower($name), self::KEYWORDS, true);
    }

    public static function isDocPseudoType(string $name): bool
    {
        $name = strtolower($name);

        return \in_array($name, self::DOC_PSEUDO_TYPES, true) || \in_array($name, self::CLASS_NAMES, true);
    }
}
