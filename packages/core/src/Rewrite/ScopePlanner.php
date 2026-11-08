<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Rewrite;

use Dpi\NamespaceShortener\Core\Analysis\ImportItem;
use Dpi\NamespaceShortener\Core\Analysis\ImportKind;
use Dpi\NamespaceShortener\Core\Analysis\InsertStyle;
use Dpi\NamespaceShortener\Core\Analysis\NameContext;
use Dpi\NamespaceShortener\Core\Analysis\NameForm;
use Dpi\NamespaceShortener\Core\Analysis\NameOccurrence;
use Dpi\NamespaceShortener\Core\Analysis\Scope;
use Dpi\NamespaceShortener\Core\Config\ImportRootSet;
use Dpi\NamespaceShortener\Core\Php\ReservedNames;

/**
 * Decides what must change in one scope, or why it cannot change.
 */
final class ScopePlanner
{
    /**
     * @var array<string, Target> by lowercase local name
     */
    private array $targets = [];

    /**
     * @var list<Edit>
     */
    private array $edits = [];

    /**
     * @var list<Conflict>
     */
    private array $conflicts = [];

    /**
     * @var array<int, list<string>> lowercase local names of the targets that usages of an import moved to
     */
    private array $movedTo = [];

    /**
     * @var array<int, true>
     */
    private array $retained = [];

    /**
     * @param list<string> $declaredClasses lowercase fully qualified names
     */
    private function __construct(
        private readonly string $code,
        private readonly Scope $scope,
        private readonly ImportRootSet $roots,
        private readonly array $declaredClasses,
    ) {
    }

    /**
     * @param list<string> $declaredClasses lowercase fully qualified names
     */
    public static function plan(string $code, Scope $scope, ImportRootSet $roots, array $declaredClasses): ScopePlan
    {
        $planner = new self($code, $scope, $roots, $declaredClasses);

        foreach ($scope->names as $name) {
            $planner->planName($name);
        }

        $removed = $planner->removedImportIds();
        $planner->detectConflicts($removed);

        if ([] !== $planner->conflicts) {
            return new ScopePlan([], $planner->conflicts);
        }

        $planner->planImports($removed);

        return new ScopePlan($planner->edits, []);
    }

    private function planName(NameOccurrence $name): void
    {
        $match = $this->isRewritable($name) ? $this->roots->match($name->resolved) : null;

        if (null === $match) {
            $this->retain($name);

            return;
        }

        $key = strtolower($match->local);
        $target = $this->targets[$key] ??= new Target($match->root, $match->local, $match->explicitAlias, $name->start);

        if (strtolower(implode('\\', $match->root)) !== $target->rootKey()) {
            $this->addConflict(ConflictKind::CompetingRoots, $name->start, \sprintf(
                'Both "%s" and "%s" would be imported as "%s". Change an import root, or give one an alias.',
                implode('\\', $target->root),
                implode('\\', $match->root),
                $target->local,
            ));
        }

        $replacement = implode('\\', [$match->local, ...$match->rest]);

        if (0 === strcasecmp($replacement, $name->text)) {
            $this->retain($name);

            return;
        }

        if ([] === $match->rest && ReservedNames::isKeyword($match->local) && NameContext::Docblock !== $name->context) {
            $this->addConflict(ConflictKind::KeywordAsBareName, $name->start, \sprintf(
                '"%s" cannot be rewritten to "%s": that is a PHP keyword when it stands alone. Use an aliased import root.',
                $name->text,
                $match->local,
            ));
        }

        $this->edits[] = new Edit($name->start, \strlen($name->text), $replacement);

        if (null !== $name->importId) {
            $this->movedTo[$name->importId][] = $key;
        }
    }

    /**
     * See docs/guide/what-gets-rewritten.md.
     */
    private function isRewritable(NameOccurrence $name): bool
    {
        if (null !== $name->importId) {
            return true;
        }

        return NameForm::FullyQualified === $name->form && NameContext::FunctionOrConstant !== $name->context;
    }

    private function retain(NameOccurrence $name): void
    {
        if (null !== $name->importId) {
            $this->retained[$name->importId] = true;
        }
    }

    /**
     * @return array<int, true>
     */
    private function removedImportIds(): array
    {
        $removed = [];

        foreach (array_keys($this->movedTo) as $importId) {
            if (!isset($this->retained[$importId])) {
                $removed[$importId] = true;
            }
        }

        return $removed;
    }

    /**
     * @param array<int, true> $removed
     */
    private function detectConflicts(array $removed): void
    {
        foreach ($this->targets as $key => $target) {
            if (ReservedNames::isReservedClassName($target->local)) {
                $this->addConflict(ConflictKind::ReservedName, $target->firstUsageStart, \sprintf(
                    'Cannot import "%s" as "%s": PHP reserves that name. Add an aliased import root for it.',
                    implode('\\', $target->root),
                    $target->local,
                ));
            }

            foreach ($this->survivingImports($removed) as $item) {
                if (strtolower($item->local) === $key && strtolower(implode('\\', $item->segments)) !== $target->rootKey()) {
                    $this->addConflict(ConflictKind::ImportCollision, $target->firstUsageStart, \sprintf(
                        'Cannot import "%s" as "%s": that name is already used by the import of "%s".',
                        implode('\\', $target->root),
                        $target->local,
                        implode('\\', $item->segments),
                    ));
                }
            }

            $implicit = strtolower(implode('\\', [...$this->scope->namespace, $target->local]));

            if ($implicit === $target->rootKey()) {
                continue;
            }

            if (\in_array($implicit, $this->declaredClasses, true)) {
                $this->addConflict(ConflictKind::DeclaredClass, $target->firstUsageStart, \sprintf(
                    'Cannot import "%s" as "%s": this file declares a class-like with that name.',
                    implode('\\', $target->root),
                    $target->local,
                ));
            }

            foreach ($this->scope->names as $name) {
                if (null === $name->importId && $name->isCapturableByImport() && strtolower($name->firstSegment()) === $key) {
                    $this->addConflict(ConflictKind::ImplicitNamespaceName, $name->start, \sprintf(
                        '"%s" means "%s" here, but importing "%s" as "%s" would change it to mean something else.',
                        $name->text,
                        implode('\\', $name->resolved),
                        implode('\\', $target->root),
                        $target->local,
                    ));
                }
            }
        }
    }

    /**
     * @param array<int, true> $removed
     */
    private function planImports(array $removed): void
    {
        $pending = [];

        foreach ($this->targets as $key => $target) {
            if (!$this->isAlreadyImported($target, $removed)) {
                $pending[$key] = $target;
            }
        }

        foreach ($this->scope->statements as $statement) {
            $lines = [];
            $changed = false;

            foreach ($statement->items as $item) {
                if (!isset($removed[$item->id])) {
                    $lines[] = $item->render();

                    continue;
                }

                $changed = true;

                foreach ($this->movedTo[$item->id] as $key) {
                    if (isset($pending[$key])) {
                        $lines[] = $pending[$key]->render();
                        unset($pending[$key]);
                    }
                }
            }

            if ($changed) {
                $this->edits[] = $this->replaceStatement($statement->start, $statement->end, $lines);
            }
        }

        if ([] !== $pending) {
            $this->edits[] = $this->insertImports($pending);
        }
    }

    /**
     * @param array<int, true> $removed
     */
    private function isAlreadyImported(Target $target, array $removed): bool
    {
        foreach ($this->survivingImports($removed) as $item) {
            if (strtolower(implode('\\', $item->segments)) === $target->rootKey() && 0 === strcasecmp($item->local, $target->local)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, true> $removed
     *
     * @return list<ImportItem>
     */
    private function survivingImports(array $removed): array
    {
        $items = [];

        foreach ($this->scope->statements as $statement) {
            foreach ($statement->items as $item) {
                if (ImportKind::Normal === $item->kind && !isset($removed[$item->id])) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * @param list<string> $lines
     */
    private function replaceStatement(int $start, int $end, array $lines): Edit
    {
        $lineStart = $this->lineStart($start);

        if ([] === $lines && '' === trim(substr($this->code, $lineStart, $start - $lineStart))) {
            // Nothing replaces the statement: take its whole line away with it.
            $newlineLength = "\r\n" === substr($this->code, $end, 2) ? 2 : strspn($this->code, "\n", $end, 1);

            return new Edit($lineStart, $end - $lineStart + $newlineLength, '');
        }

        $indent = substr($this->code, $lineStart, $start - $lineStart);
        $indent = '' === trim($indent) ? $indent : '';

        return new Edit($start, $end - $start, implode($this->newline() . $indent, $lines));
    }

    /**
     * @param array<string, Target> $pending
     */
    private function insertImports(array $pending): Edit
    {
        $newline = $this->newline();
        $lastStatement = [] === $this->scope->statements ? null : $this->scope->statements[array_key_last($this->scope->statements)];
        $rendered = array_values(array_map(static fn (Target $target): string => $target->render(), $pending));

        if (null !== $lastStatement) {
            $lineStart = $this->lineStart($lastStatement->start);
            $indent = substr($this->code, $lineStart, $lastStatement->start - $lineStart);
            $indent = '' === trim($indent) ? $indent : '';

            return new Edit($lastStatement->end, 0, $newline . $indent . implode($newline . $indent, $rendered));
        }

        $indent = $this->scope->indent;
        $block = $indent . implode($newline . $indent, $rendered);

        return new Edit($this->scope->insertAt, 0, match ($this->scope->insertStyle) {
            InsertStyle::AfterStatement => $newline . $newline . $block,
            InsertStyle::AfterBrace => $newline . $block . $newline,
            InsertStyle::AfterOpenTag => $newline . $block . $newline,
        });
    }

    private function lineStart(int $offset): int
    {
        $newline = strrpos(substr($this->code, 0, $offset), "\n");

        return false === $newline ? 0 : $newline + 1;
    }

    private function newline(): string
    {
        return str_contains($this->code, "\r\n") ? "\r\n" : "\n";
    }

    private function addConflict(ConflictKind $kind, int $offset, string $message): void
    {
        $this->conflicts[] = new Conflict($kind, substr_count($this->code, "\n", 0, $offset) + 1, $message);
    }
}
