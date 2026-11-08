<?php

/*
 * This file is part of the dpi/namespace-shortener-phpcs project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

/*
 * Loaded by Composer's autoloader, which PHP_CodeSniffer includes before its own Tokens class.
 *
 * On a PHP without its newest tokens, PHP_CodeSniffer defines them as strings, unless they're already defined. PHP-Parser
 * throws an error if it finds that, as it requires integers. Letting it go first gives both of them integers.
 */
class_exists(PhpParser\Lexer::class);
