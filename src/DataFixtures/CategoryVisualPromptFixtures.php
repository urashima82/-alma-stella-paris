<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CategoryVisualPrompt;
use App\Entity\ProductCategory;
use App\Enum\VisualType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CategoryVisualPromptFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $this->loadPreservationInstructions();
        $this->loadVisualPrompts($manager);

        $manager->flush();
    }

    /** @return list<class-string<Fixture>> */
    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }

    private function loadPreservationInstructions(): void
    {
        /** @var ProductCategory $rings */
        $rings = $this->getReference('category-Rings', ProductCategory::class);
        $rings->setPreservationInstructions(
            'PRESERVE EXACTLY: the band thickness, the exact setting, any engravings, the central element, and all metal finishes. Do not modify ring size proportions or stone placement.'
        );

        /** @var ProductCategory $earrings */
        $earrings = $this->getReference('category-Earrings', ProductCategory::class);
        $earrings->setPreservationInstructions(
            'PRESERVE EXACTLY: the hook/clasp mechanism, the drop length, the hoop diameter, any chain links, and all stone settings. Do not alter earring proportions or symmetry.'
        );

        /** @var ProductCategory $bracelets */
        $bracelets = $this->getReference('category-Bracelets', ProductCategory::class);
        $bracelets->setPreservationInstructions(
            'PRESERVE EXACTLY: the clasp style, chain link pattern, bangle thickness, stone bead arrangement, and all metal textures. Do not modify bracelet diameter or closure mechanism.'
        );

        /** @var ProductCategory $necklaces */
        $necklaces = $this->getReference('category-Necklaces', ProductCategory::class);
        $necklaces->setPreservationInstructions(
            'PRESERVE EXACTLY: the pendant shape and size, chain thickness, necklace length proportions, layering arrangement, and all stone or charm attachments.'
        );
    }

    private function loadVisualPrompts(ObjectManager $manager): void
    {
        $prompts = $this->getPromptData();

        foreach ($prompts as $data) {
            /** @var ProductCategory $category */
            $category = $this->getReference('category-'.$data['category'], ProductCategory::class);

            $prompt = new CategoryVisualPrompt();
            $prompt->setCategory($category);
            $prompt->setVisualType($data['type']);
            $prompt->setFraming($data['framing']);
            $prompt->setStaging($data['staging']);
            $prompt->setProps($data['props']);
            $prompt->setActive(true);
            $prompt->setVersion(1);
            $manager->persist($prompt);
        }
    }

    /**
     * @return list<array{category: string, type: VisualType, framing: string, staging: string, props: string[]}>
     */
    private function getPromptData(): array
    {
        return [
            // --- Rings ---
            [
                'category' => 'Rings',
                'type' => VisualType::Vignette,
                'framing' => 'close-up, ring shown from frontal 3/4 angle, centered in frame',
                'staging' => 'floating on subtle shadow with soft professional lighting, slight elevation',
                'props' => ['polished marble surface', 'draped silk fabric', 'velvet cushion'],
            ],
            [
                'category' => 'Rings',
                'type' => VisualType::Worn,
                'framing' => 'on a feminine hand with natural elegant pose, hand framed from wrist, waist-up composition',
                'staging' => 'soft neutral background with warm undertones, editorial portrait style, shallow depth of field',
                'props' => ['softly blurred indoor scene', 'warm ambient light'],
            ],
            [
                'category' => 'Rings',
                'type' => VisualType::Lifestyle,
                'framing' => 'ring as clear focal point, styled within a contextual scene',
                'staging' => 'vanity table scene with perfume bottles and jewelry, soft morning light from window',
                'props' => ['vintage perfume bottle', 'linen fabric', 'dried flowers', 'gold tray'],
            ],
            // --- Earrings ---
            [
                'category' => 'Earrings',
                'type' => VisualType::Vignette,
                'framing' => 'pair of earrings centered, symmetrical layout, close-up showing both pieces',
                'staging' => 'on a clean matte surface with soft directional lighting highlighting metallic details',
                'props' => ['cream linen surface', 'subtle shadow', 'soft reflection'],
            ],
            [
                'category' => 'Earrings',
                'type' => VisualType::Worn,
                'framing' => 'profile or 3/4 face portrait showing earring on the model, neck and jawline visible',
                'staging' => 'neutral warm-toned background, soft hair pulled back to reveal the earring, editorial beauty style',
                'props' => ['soft natural light', 'blurred shoulder line'],
            ],
            [
                'category' => 'Earrings',
                'type' => VisualType::Lifestyle,
                'framing' => 'earrings as focal point within a styled flat-lay or dressing table scene',
                'staging' => 'elegant dressing table with soft morning light, feminine atmosphere',
                'props' => ['small mirror', 'fresh flowers', 'porcelain dish', 'silk scarf'],
            ],
            // --- Bracelets ---
            [
                'category' => 'Bracelets',
                'type' => VisualType::Vignette,
                'framing' => 'bracelet laid flat or draped in a gentle curve, full piece visible, close-up',
                'staging' => 'on a warm neutral surface with soft side lighting emphasizing texture and stones',
                'props' => ['raw linen fabric', 'natural stone slab', 'warm shadow'],
            ],
            [
                'category' => 'Bracelets',
                'type' => VisualType::Worn,
                'framing' => 'on a feminine wrist in a natural relaxed pose, hand gently resting or reaching',
                'staging' => 'soft warm background, editorial lifestyle feel, shallow depth of field on the wrist',
                'props' => ['blurred café or garden background', 'warm ambient light'],
            ],
            [
                'category' => 'Bracelets',
                'type' => VisualType::Lifestyle,
                'framing' => 'bracelet as focal point in a bohemian or travel-inspired scene',
                'staging' => 'sunlit scene with natural materials, warm earthy tones, relaxed everyday luxury',
                'props' => ['woven basket', 'terracotta pot', 'dried palm leaf', 'ceramic cup'],
            ],
            // --- Necklaces ---
            [
                'category' => 'Necklaces',
                'type' => VisualType::Vignette,
                'framing' => 'necklace arranged in a natural drape or gentle curve, pendant centered, full chain visible',
                'staging' => 'on a soft neutral surface with diffused overhead lighting, elegant simplicity',
                'props' => ['ivory velvet surface', 'soft fabric folds', 'subtle gold accents'],
            ],
            [
                'category' => 'Necklaces',
                'type' => VisualType::Worn,
                'framing' => 'on the décolletage area, collarbone to chest visible, soft elegant pose',
                'staging' => 'neutral warm background, soft focus on face, editorial beauty lighting on the necklace',
                'props' => ['minimal clothing neckline', 'soft hair draping', 'warm skin tones'],
            ],
            [
                'category' => 'Necklaces',
                'type' => VisualType::Lifestyle,
                'framing' => 'necklace as focal point in a sophisticated interior scene',
                'staging' => 'Parisian apartment or boutique setting with soft afternoon light, refined atmosphere',
                'props' => ['marble console', 'art book', 'candle', 'fresh peonies'],
            ],
        ];
    }
}
