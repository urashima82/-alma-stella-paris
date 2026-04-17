<?php

declare(strict_types=1);

namespace App\Enum;

enum VisualStatus: string
{
    case Generating = 'generating';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Generating => 'En génération',
            self::PendingReview => 'En attente de revue',
            self::Approved => 'Approuvé',
            self::Rejected => 'Rejeté',
            self::Failed => 'Échoué',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Generating => '#6366f1',
            self::PendingReview => '#f59e0b',
            self::Approved => '#22c55e',
            self::Rejected => '#ef4444',
            self::Failed => '#dc2626',
        };
    }
}
