<?php

declare(strict_types=1);

namespace App\Service\Content;

final readonly class ContentPromptResult
{
    public function __construct(
        public string $content,
        public bool $usedFallback,
    ) {
    }
}
