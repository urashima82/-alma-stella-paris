<?php

declare(strict_types=1);

namespace App\Service\Content;

/**
 * Editorial voice for product text content (FR + EN).
 *
 * Distinct from BrandStyleProvider (visual voice) — kept separate so the two
 * AI pipelines (visual M16 / content M17) stay independent.
 */
final class ContentBrandVoiceProvider
{
    public function getEditorialVoice(): string
    {
        return <<<'TXT'
            BRAND VOICE — Alma Stella Paris is a French jewelry maison curating water-resistant stainless-steel pieces with natural stones, sourced between Paris and Mexico.

            TONE:
            - Discreet luxury, never ostentatious. The reader should feel invited, not sold to.
            - Subtly poetic, sensorial — favour evocations of light, texture, gesture, daily ritual.
            - Confident and economical: each sentence earns its place. No filler, no superlatives.
            - Never use "élégant", "magnifique", "sublime", "exceptionnel", "stunning", "gorgeous", "perfect" — they are empty.
            - Avoid clichés ("intemporel", "must-have", "incontournable", "timeless classic").
            - Speak to a woman who already knows what she likes; do not explain or justify.

            STRUCTURE FOR DESCRIPTIONS:
            - Two short paragraphs maximum. Roughly 50–90 words total.
            - Open on what the eye sees first: the form, the play of light, the stone.
            - Close on the wearing: the gesture, the daily life, the pairing.
            - One concrete sensory detail per paragraph. No bullet lists.

            STRUCTURE FOR NAMES:
            - 2 to 4 words. Evocative, never literal.
            - French: noun phrase (e.g. "Bague Murmure Lunaire").
            - English: same register, equally short (e.g. "Lunar Whisper Ring").
            - Names need not be literal translations — preserve the poetic intent.
            TXT;
    }
}
