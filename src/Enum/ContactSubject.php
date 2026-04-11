<?php

declare(strict_types=1);

namespace App\Enum;

enum ContactSubject: string
{
    case General = 'general';
    case Order = 'order';
    case Return = 'return';
    case Collaboration = 'collaboration';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::General => 'contact.subject.general',
            self::Order => 'contact.subject.order',
            self::Return => 'contact.subject.return',
            self::Collaboration => 'contact.subject.collaboration',
            self::Other => 'contact.subject.other',
        };
    }
}
