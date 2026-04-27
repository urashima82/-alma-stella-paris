<?php

declare(strict_types=1);

namespace App\Enum;

enum ContentSuggestionStatus: string
{
    case Generating = 'generating';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Applied = 'applied';

    public function label(): string
    {
        return match ($this) {
            self::Generating => 'En génération',
            self::Pending => 'En attente',
            self::Approved => 'Approuvée',
            self::Rejected => 'Rejetée',
            self::Applied => 'Appliquée',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Generating => '#6366f1',
            self::Pending => '#f59e0b',
            self::Approved => '#22c55e',
            self::Rejected => '#ef4444',
            self::Applied => '#3b82f6',
        };
    }
}
