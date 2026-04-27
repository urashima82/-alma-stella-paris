<?php

declare(strict_types=1);

namespace App\Service\Generator;

use App\Enum\VisualType;

interface VisualGeneratorInterface
{
    /**
     * @param string[] $sourceBase64
     *
     * @throws VisualGenerationException
     */
    public function generate(
        string $prompt,
        array $sourceBase64,
        VisualType $visualType,
    ): GeneratedVisualResult;
}
