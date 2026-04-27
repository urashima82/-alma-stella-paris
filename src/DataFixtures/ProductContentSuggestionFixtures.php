<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\ProductContentSuggestion;
use App\Enum\ContentSuggestionStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProductContentSuggestionFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $productRepository = $manager->getRepository(Product::class);

        $products = $productRepository->findBy([], ['id' => 'ASC'], 2);

        if (\count($products) === 0) {
            return;
        }

        $pendingSuggestion = new ProductContentSuggestion();
        $pendingSuggestion->setProduct($products[0]);
        $pendingSuggestion->setNameEn('Lunar Whisper Ring');
        $pendingSuggestion->setNameFr('Bague Murmure Lunaire');
        $pendingSuggestion->setDescriptionEn('A delicate band cradling a moonstone whose silvery glow seems to capture the breath of dawn. A discreet piece, made to be worn every day.');
        $pendingSuggestion->setDescriptionFr('Un anneau délicat berçant une pierre de lune dont l\'éclat argenté semble capturer le souffle de l\'aube. Une pièce discrète, faite pour être portée chaque jour.');
        $pendingSuggestion->setStatus(ContentSuggestionStatus::Pending);
        $pendingSuggestion->setModelUsed('gemini-2.5-flash');
        $pendingSuggestion->setRequestId('demo-pending-001');
        $pendingSuggestion->setContextSnapshot([
            'category' => [
                'name_fr' => 'Bagues',
                'parent_name_fr' => null,
            ],
            'stones' => [
                ['name_fr' => 'Pierre de Lune', 'color' => 'Blanc nacré', 'virtues' => 'Intuition, douceur'],
            ],
        ]);
        $manager->persist($pendingSuggestion);

        if (isset($products[1])) {
            $appliedSuggestion = new ProductContentSuggestion();
            $appliedSuggestion->setProduct($products[1]);
            $appliedSuggestion->setNameEn($products[1]->getName());
            $appliedSuggestion->setNameFr($products[1]->getNameFr());
            $appliedSuggestion->setDescriptionEn($products[1]->getDescription());
            $appliedSuggestion->setDescriptionFr($products[1]->getDescriptionFr());
            $appliedSuggestion->setStatus(ContentSuggestionStatus::Applied);
            $appliedSuggestion->setModelUsed('gemini-2.5-flash');
            $appliedSuggestion->setRequestId('demo-applied-001');
            $appliedSuggestion->setContextSnapshot([
                'category' => [
                    'name_fr' => $products[1]->getCategory()?->getNameFr() ?? '—',
                    'parent_name_fr' => $products[1]->getCategory()?->getParent()?->getNameFr(),
                ],
                'stones' => [],
            ]);
            $appliedSuggestion->setAppliedAt(new \DateTimeImmutable('-2 days'));
            $manager->persist($appliedSuggestion);
        }

        $manager->flush();
    }

    /** @return list<class-string<Fixture>> */
    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }
}
