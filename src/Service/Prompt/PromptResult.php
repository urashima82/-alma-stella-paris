<?php

declare(strict_types=1);

namespace App\Service\Prompt;

final readonly class PromptResult
{
    public function __construct(
        public string $content,
        public int $categoryPromptVersion,
        public bool $usedFallback,
    ) {
    }
}
