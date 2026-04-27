<?php

declare(strict_types=1);

namespace App\Service\Generator;

use App\Enum\VisualType;

final class VisualGeneratorRouter
{
    public function __construct(
        private readonly GeminiVisualGenerator $geminiPro,
    ) {
    }

    public function for(VisualType $type): VisualGeneratorInterface
    {
        return match ($type) {
            VisualType::Vignette, VisualType::Worn, VisualType::Lifestyle => $this->geminiPro,
        };
    }
}
