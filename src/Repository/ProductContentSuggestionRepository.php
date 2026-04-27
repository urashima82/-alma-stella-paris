<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductContentSuggestion;
use App\Enum\ContentSuggestionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductContentSuggestion>
 */
class ProductContentSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductContentSuggestion::class);
    }

    /**
     * Returns the most recent suggestion that is still actionable (Generating
     * or Pending) — i.e. either being processed by the worker or awaiting
     * human review. Used to render the workspace card.
     */
    public function findLatestActiveForProduct(Product $product): ?ProductContentSuggestion
    {
        /** @var ProductContentSuggestion|null $result */
        $result = $this->createQueryBuilder('s')
            ->andWhere('s.product = :product')
            ->andWhere('s.status IN (:statuses)')
            ->orderBy('s.generatedAt', 'DESC')
            ->setMaxResults(1)
            ->setParameter('product', $product)
            ->setParameter('statuses', [
                ContentSuggestionStatus::Generating,
                ContentSuggestionStatus::Pending,
            ])
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    /**
     * @return ProductContentSuggestion[]
     */
    public function findActiveForProduct(Product $product): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.product = :product')
            ->andWhere('s.status IN (:statuses)')
            ->orderBy('s.generatedAt', 'DESC')
            ->setParameter('product', $product)
            ->setParameter('statuses', [
                ContentSuggestionStatus::Generating,
                ContentSuggestionStatus::Pending,
            ])
            ->getQuery()
            ->getResult();
    }

    public function hasGeneratingSuggestion(Product $product): bool
    {
        $count = (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.product = :product')
            ->andWhere('s.status = :status')
            ->setParameter('product', $product)
            ->setParameter('status', ContentSuggestionStatus::Generating)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
