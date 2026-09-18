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
            - Shape: piece type, then proper name. The type is drawn from the category
              path given below — the family word, then the sub-category word.
              French "Bracelet Jonc Elsa", English "Elsa Chain Bracelet".
              Keep the type to one or two words: say "Bracelet Jonc", not
              "Bracelet Jonc Simple". Drop the qualifier, never the substance.
            - The proper name is the SAME in both languages — it is a name, it does not
              translate. Only the type words change.
              Sole exception: a place with an established form in each language keeps it
              (Ravenne/Ravenna, Hawaï/Hawaii).
            - Draw the proper name from feminine first names and evocative place names,
              mixed freely: Marina, Barbara, Livia, Elsa, Ostende, Ravenne, Hawaï.
            - Let the photos guide the choice when they can — a colour, a light, a shore
              the piece calls to mind. A name that merely sounds right is still better
              than one already taken.
            - Only the WHOLE name has to be new. The same proper name may reappear under
              another sub-category: "Bracelet Jonc Elsa" and "Bracelet Chaîne Elsa" are
              two distinct names, and that is welcome — it reads as a collection.
            - FORBIDDEN, no exception: registered brands (Chanel, Cartier, Tiffany, Dior,
              Swarovski…), identifiable living or recent public figures, and religious
              figures.
            TXT;
    }
}
