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

    /**
     * Angles proposed to the nth staged photo, in the order a piece is
     * naturally shot. Defaults only — the tray keeps every angle editable, and
     * a product may hold fewer photos than this sequence has entries.
     *
     * @return list<self>
     */
    public static function defaultSequence(): array
    {
        return [self::Front, self::ThreeQuarter, self::Detail, self::Back];
    }

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
