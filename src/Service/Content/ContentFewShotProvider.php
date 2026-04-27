<?php

declare(strict_types=1);

namespace App\Service\Content;

/**
 * Few-shot training pairs (text-only, no images) sampled from the catalogue
 * to anchor the model's voice. Independent of the visual pipeline.
 *
 * Examples are intentionally hand-picked to span the four main categories
 * (rings, earrings, bracelets, necklaces) and to mix "with named stone" /
 * "without identified stone" scenarios so the model learns both fallbacks.
 */
final class ContentFewShotProvider
{
    /**
     * @return list<array{category: string, stones: string, name_fr: string, name_en: string, description_fr: string, description_en: string}>
     */
    public function getExamples(): array
    {
        return [
            [
                'category' => 'Bagues',
                'stones' => 'Pierre de Lune',
                'name_fr' => 'Bague Reflet Lunaire',
                'name_en' => 'Moonlit Reflection Ring',
                'description_fr' => "Une pierre de lune sertie sobrement, dont les nuances irisées évoluent au fil du jour. La monture en acier inoxydable laisse toute sa présence à la pierre.\n\nUn anneau pour celles qui aiment les bijoux qui se laissent oublier, jusqu'à ce qu'un éclat les rappelle au regard.",
                'description_en' => "A moonstone set with restraint, its iridescent shifts changing with the light. The stainless-steel band keeps its place, letting the stone speak.\n\nA ring for those who love pieces that fade into the day until a glimmer pulls the eye back.",
            ],
            [
                'category' => 'Boucles d\'oreilles',
                'stones' => 'Lapis Lazuli',
                'name_fr' => 'Boucles Bleu Profond',
                'name_en' => 'Deep Blue Drops',
                'description_fr' => "Le lapis-lazuli, d'un bleu nuit constellé d'or, suspendu à une fine attache d'acier. La proportion reste mesurée : une présence à hauteur d'oreille, jamais imposante.\n\nÀ porter sur un col blanc ou contre une peau hâlée, là où la couleur trouve son terrain de jeu.",
                'description_en' => "Lapis lazuli, deep night-blue scattered with gold, hangs from a slim steel hook. The proportion stays measured — present at the ear, never overstated.\n\nWear them against a white collar or sun-warmed skin, where the colour finds room to breathe.",
            ],
            [
                'category' => 'Bracelets',
                'stones' => '',
                'name_fr' => 'Bracelet Ligne Continue',
                'name_en' => 'Continuous Line Bracelet',
                'description_fr' => "Une chaîne d'acier polie, sans rupture, qui suit le poignet d'un mouvement souple. Le fermoir s'efface, prolonge le dessin.\n\nIl s'oublie sous une manche, puis se redécouvre quand la main se tend. Une pièce pour tous les jours, et pour longtemps.",
                'description_en' => "A polished steel chain, unbroken, following the wrist with a quiet flex. The clasp disappears into the line.\n\nIt slips under a sleeve and resurfaces when the hand reaches out. A piece for every day, and for years.",
            ],
            [
                'category' => 'Colliers',
                'stones' => 'Onyx noir',
                'name_fr' => 'Collier Trait Noir',
                'name_en' => 'Black Line Necklace',
                'description_fr' => "Un onyx noir taillé court, posé sur la clavicule par une chaîne d'acier fine. Le contraste est net, presque graphique.\n\nIl se porte seul, contre une peau nue, ou superposé à une chaîne plus longue pour jouer les profondeurs.",
                'description_en' => "A black onyx, short-cut, settling on the collarbone from a slender steel chain. The contrast is clean, almost graphic.\n\nWear it alone against bare skin, or layered over a longer chain to play with depth.",
            ],
        ];
    }

    public function renderForPrompt(): string
    {
        $blocks = [];
        foreach ($this->getExamples() as $i => $example) {
            $stones = $example['stones'] !== '' ? $example['stones'] : '(none identified)';
            $blocks[] = \sprintf(
                "EXAMPLE %d\n  category: %s\n  stones: %s\n  expected output:\n  {\n    \"nameFr\": \"%s\",\n    \"nameEn\": \"%s\",\n    \"descriptionFr\": \"%s\",\n    \"descriptionEn\": \"%s\"\n  }",
                $i + 1,
                $example['category'],
                $stones,
                $this->escape($example['name_fr']),
                $this->escape($example['name_en']),
                $this->escape($example['description_fr']),
                $this->escape($example['description_en']),
            );
        }

        return \implode("\n\n", $blocks);
    }

    private function escape(string $value): string
    {
        return \str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value);
    }
}
