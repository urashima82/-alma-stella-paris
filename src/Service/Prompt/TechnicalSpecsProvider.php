<?php

declare(strict_types=1);

namespace App\Service\Prompt;

use App\Enum\VisualType;

final class TechnicalSpecsProvider
{
    public function getSpecsFor(VisualType $type): string
    {
        $base = "TECHNICAL SPECS:\n"
            ."- Output format: portrait 4:5\n"
            ."- Resolution: 819x1024\n";

        return $base.match ($type) {
            VisualType::Vignette => "- Style: editorial jewelry catalog, magazine quality, packshot\n"
                ."- Sharp focus on the jewelry, professional product photography\n"
                .'- Clean, distraction-free composition',
            VisualType::Worn => "- Style: editorial portrait, fashion catalog quality\n"
                ."- Natural skin tones, realistic proportions\n"
                .'- Sharp focus on the jewelry piece, slight bokeh on model',
            VisualType::Lifestyle => "- Style: editorial lifestyle, interior/mood photography\n"
                ."- Shallow depth of field, jewelry as clear focal point\n"
                .'- Warm ambient lighting, natural materials',
        };
    }
}
