<?php

/*
 * This file is part of the dpi/namespace-shortener-phpcs project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\PhpCodeSniffer\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;

use function Safe\file_get_contents;

/**
 * Other sniffs must not undo this one, and the combination must settle.
 */
final class OtherSniffsTest extends CliTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function rules(): iterable
    {
        yield 'PSR1' => ['<rule ref="PSR1"/>'];
        yield 'PSR2' => ['<rule ref="PSR2"/>'];
        yield 'PSR12' => ['<rule ref="PSR12"/>'];
        yield 'PEAR' => ['<rule ref="PEAR"/>'];
        yield 'Squiz' => ['<rule ref="Squiz"/>'];
        yield 'PSR12.Files.ImportStatement' => ['<rule ref="PSR12.Files.ImportStatement"/>'];
        yield 'PSR2.Namespaces.UseDeclaration' => ['<rule ref="PSR2.Namespaces.UseDeclaration"/>'];
    }

    #[DataProvider('rules')]
    public function testRuleSurvivesAlongsideOtherSniffsAndSettles(string $rules): void
    {
        $this->writeRuleset(['A\B\C\*'], $rules);
        $path = $this->writeSource(<<<'PHP'
            <?php

            namespace App;

            use A\B\C\D\E;
            use A\B\C\D\F;

            /**
             * @param \A\B\C\D\G $g
             */
            function run(E $e, $g): F
            {
                return new F($e, new \A\B\C\D\H());
            }

            PHP);

        $this->runTool('phpcbf');

        $code = file_get_contents($path);
        self::assertStringContainsString('use A\B\C\D;', $code);
        self::assertStringContainsString('D\E $e', $code);
        self::assertStringContainsString('new D\F(', $code);
        self::assertStringContainsString('new D\H(', $code);
        self::assertStringNotContainsString('use A\B\C\D\E;', $code);

        $ours = array_filter($this->findings(), static fn (array $finding): bool => str_starts_with($finding['source'], self::SNIFF));
        self::assertSame([], $ours);

        $this->runTool('phpcbf');
        self::assertSame($code, file_get_contents($path));
    }
}
