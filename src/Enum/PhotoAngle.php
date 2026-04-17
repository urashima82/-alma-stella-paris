<?php

declare(strict_types=1);

namespace App\Enum;

enum PhotoAngle: string
{
    case Front = 'front';
    case ThreeQuarter = 'three_quarter';
    case Detail = 'detail';
    case Back = 'back';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Front => 'Face',
            self::ThreeQuarter => 'Trois-quarts',
            self::Detail => 'Détail',
            self::Back => 'Dos',
            self::Other => 'Autre',
        };
    }
}
