<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Analysis;

/**
 * A name in source code, and the symbol it resolves to.
 */
final readonly class NameOccurrence
{
    /**
     * @param non-empty-list<string> $resolved the fully qualified name, without a leading separator
     * @param ?int $importId the import the name resolves through, if any
     */
    public function __construct(
        public int $start,
        public string $text,
        public NameForm $form,
        public NameContext $context,
        public array $resolved,
        public ?int $importId,
    ) {
    }

    public function firstSegment(): string
    {
        return explode('\\', ltrim($this->text, '\\'), 2)[0];
    }

    /**
     * Whether an import could capture this name if one were added.
     */
    public function isCapturableByImport(): bool
    {
        if (NameForm::Qualified === $this->form) {
            return true;
        }

        return NameForm::Unqualified === $this->form && NameContext::FunctionOrConstant !== $this->context;
    }
}
