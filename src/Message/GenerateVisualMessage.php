<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\VisualType;

final readonly class GenerateVisualMessage
{
    /**
     * @param int|null $visualId If set, the handler updates this existing
     *                           GeneratedVisual entity instead of creating a new one.
     *                           Producers (controllers) should always pre-create the
     *                           entity in `Generating` status and pass its id, so
     *                           concurrent clicks cannot produce duplicate rows for
     *                           the same (product, type, variant) tuple.
     */
    public function __construct(
        public int $productId,
        public VisualType $type,
        public int $variantNumber,
        public ?int $visualId = null,
    ) {
    }
}
