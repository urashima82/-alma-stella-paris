<?php

declare(strict_types=1);

namespace App\Message;

final readonly class FillProductContentMessage
{
    /**
     * @param int|null    $suggestionId      If set, the handler updates this existing
     *                                       ProductContentSuggestion (status=Pending) instead
     *                                       of creating a new one. Producers always pre-create
     *                                       the entity so the UI sees an in-flight row immediately.
     * @param string|null $additionalContext free-form steering text passed when the
     *                                       manager hits "Regenerate" with a tweak
     */
    public function __construct(
        public int $productId,
        public ?int $suggestionId = null,
        public ?string $additionalContext = null,
    ) {
    }
}
