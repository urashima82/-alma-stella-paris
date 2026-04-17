<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\VisualType;

final readonly class GenerateVisualMessage
{
    public function __construct(
        public int $productId,
        public VisualType $type,
        public int $variantNumber,
    ) {
    }
}
