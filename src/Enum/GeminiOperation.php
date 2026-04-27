<?php

declare(strict_types=1);

namespace App\Enum;

enum GeminiOperation: string
{
    case Visual = 'visual';
    case TextFill = 'text_fill';

    public function label(): string
    {
        return match ($this) {
            self::Visual => 'Visuel',
            self::TextFill => 'Contenu',
        };
    }
}
