<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\ContentSuggestionStatus;
use PHPUnit\Framework\TestCase;

final class ContentSuggestionStatusTest extends TestCase
{
    public function testEveryCaseHasLabelAndBadgeColor(): void
    {
        foreach (ContentSuggestionStatus::cases() as $case) {
            self::assertNotEmpty($case->label());
            self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $case->badgeColor());
        }
    }

    public function testWorkflowOrder(): void
    {
        // Generating → Pending → (Approved | Rejected | Applied) — order matters
        // because the workspace logic relies on `Generating` being the only active
        // pre-review state and `Pending` being the only "ready for review" state.
        self::assertSame('generating', ContentSuggestionStatus::Generating->value);
        self::assertSame('pending', ContentSuggestionStatus::Pending->value);
        self::assertSame('applied', ContentSuggestionStatus::Applied->value);
    }
}
