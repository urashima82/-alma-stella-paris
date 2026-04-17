<?php

declare(strict_types=1);

namespace App\Enum;

enum VisualWorkflowStatus: string
{
    case Draft = 'draft';
    case PendingVisuals = 'pending_visuals';
    case ReadyForReview = 'ready_for_review';
    case VisualsApproved = 'visuals_approved';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::PendingVisuals => 'Visuels en cours',
            self::ReadyForReview => 'Prêt pour revue',
            self::VisualsApproved => 'Visuels approuvés',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => '#9ca3af',
            self::PendingVisuals => '#6366f1',
            self::ReadyForReview => '#f59e0b',
            self::VisualsApproved => '#22c55e',
        };
    }
}
