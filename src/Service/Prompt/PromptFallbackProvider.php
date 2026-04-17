<?php

declare(strict_types=1);

namespace App\Service\Prompt;

use App\Entity\CategoryVisualPrompt;
use App\Enum\VisualType;

final class PromptFallbackProvider
{
    public function getGenericPromptFor(VisualType $type): CategoryVisualPrompt
    {
        $prompt = new CategoryVisualPrompt();
        $prompt->setVisualType($type);
        $prompt->setVersion(0);
        $prompt->setActive(true);

        match ($type) {
            VisualType::Vignette => $this->configureVignette($prompt),
            VisualType::Worn => $this->configureWorn($prompt),
            VisualType::Lifestyle => $this->configureLifestyle($prompt),
        };

        return $prompt;
    }

    private function configureVignette(CategoryVisualPrompt $prompt): void
    {
        $prompt->setFraming('centered product shot, full piece visible');
        $prompt->setStaging('on clean neutral surface with soft professional lighting');
        $prompt->setProps(['white surface', 'soft shadow']);
    }

    private function configureWorn(CategoryVisualPrompt $prompt): void
    {
        $prompt->setFraming('product worn by a model in natural pose');
        $prompt->setStaging('neutral background with warm undertones, editorial portrait style');
        $prompt->setProps(['soft natural light']);
    }

    private function configureLifestyle(CategoryVisualPrompt $prompt): void
    {
        $prompt->setFraming('product in contextual scene, shallow depth of field');
        $prompt->setStaging('elegant everyday setting with ambient warm light');
        $prompt->setProps(['natural materials', 'soft bokeh']);
    }
}
