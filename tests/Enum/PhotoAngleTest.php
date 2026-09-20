<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Entity\SourcePhoto;
use App\Enum\PhotoAngle;
use PHPUnit\Framework\TestCase;

final class PhotoAngleTest extends TestCase
{
    /**
     * The photo tray walks this sequence to pre-assign an angle to the nth
     * staged photo, so it has to cover a full set of source photos without
     * repeating itself — otherwise two photos land on the same angle by default.
     */
    public function testDefaultSequenceCoversAFullSetWithoutRepeating(): void
    {
        $sequence = PhotoAngle::defaultSequence();

        self::assertCount(SourcePhoto::MAX_PER_PRODUCT, $sequence);
        self::assertSame($sequence, \array_values(\array_unique($sequence, \SORT_REGULAR)));
        self::assertSame(PhotoAngle::Front, $sequence[0]);
    }

    public function testEveryAngleIsLabelled(): void
    {
        foreach (PhotoAngle::cases() as $angle) {
            self::assertNotSame('', $angle->label());
        }
    }
}
