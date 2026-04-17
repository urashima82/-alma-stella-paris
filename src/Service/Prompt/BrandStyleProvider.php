<?php

declare(strict_types=1);

namespace App\Service\Prompt;

final class BrandStyleProvider
{
    public function getBrandStyle(): string
    {
        return 'BRAND IDENTITY: French jewelry maison aesthetic. '
            .'Timeless elegance, natural warm lighting, neutral sophisticated palette '
            .'(ivory, warm beige, soft gold undertones), editorial catalog quality '
            .'reminiscent of Dinh Van and Mauboussin. '
            .'Stainless steel jewelry with natural stones, water-resistant, '
            .'curated between Paris and Mexico.';
    }
}
