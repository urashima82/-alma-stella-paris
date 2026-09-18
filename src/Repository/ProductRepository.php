<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\Stone;
use App\Enum\VisualWorkflowStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return Product[]
     */
    /**
     * @return Product[]
     */
    public function findFeatured(int $limit = 4, ?string $collection = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = true')
            ->andWhere('p.isFeatured = true')
            ->andWhere('p.isSoldOut = false')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($collection !== null && $collection !== 'all') {
            $qb->andWhere('JSON_CONTAINS(p.availableIn, :collection) = 1')
                ->setParameter('collection', \sprintf('"%s"', $collection));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Product[]
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = true')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countRecentlySold(int $days = 7): int
    {
        $since = new \DateTimeImmutable(\sprintf('-%d days', $days));

        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.isSoldOut = true')
            ->andWhere('p.soldAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAvailable(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.isPublished = true')
            ->andWhere('p.isSoldOut = false')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Product[]
     */
    public function findLatestAvailable(int $limit = 4, ?string $collection = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = true')
            ->andWhere('p.isSoldOut = false')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($collection !== null && $collection !== 'all') {
            $qb->andWhere('JSON_CONTAINS(p.availableIn, :collection) = 1')
                ->setParameter('collection', \sprintf('"%s"', $collection));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param list<Stone> $stones
     */
    public function findVisibleQuery(?ProductCategory $category = null, ?string $collection = null, array $stones = [], bool $noStone = false): QueryBuilder
    {
        $soldOutCutoff = new \DateTimeImmutable('-14 days');

        $qb = $this->createQueryBuilder('p')
            ->join('p.category', 'c')
            ->andWhere('p.isPublished = true')
            ->andWhere('p.isSoldOut = false OR (p.isSoldOut = true AND p.soldAt > :soldOutCutoff)')
            ->setParameter('soldOutCutoff', $soldOutCutoff)
            ->orderBy('p.isSoldOut', 'ASC')
            ->addOrderBy('p.isFeatured', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC');

        if ($category !== null) {
            if ($category->hasChildren()) {
                // Parent category: show products from all children
                $qb->andWhere('c.parent = :parentCategory')
                    ->setParameter('parentCategory', $category);
            } else {
                $qb->andWhere('p.category = :category')
                    ->setParameter('category', $category);
            }
        }

        if ($collection !== null && $collection !== 'all') {
            $qb->andWhere('JSON_CONTAINS(p.availableIn, :collection) = 1')
                ->setParameter('collection', \sprintf('"%s"', $collection));
        }

        if ($stones !== []) {
            $qb->join('p.stones', 'st')
                ->andWhere('st IN (:stones)')
                ->setParameter('stones', $stones)
                ->groupBy('p.id');
        } elseif ($noStone) {
            $qb->leftJoin('p.stones', 'st_none')
                ->andWhere('st_none.id IS NULL');
        }

        return $qb;
    }

    public function countExpiringSoldSoon(int $expiryDays = 14, int $warningDays = 3): int
    {
        $expiryThreshold = new \DateTimeImmutable(\sprintf('-%d days', $expiryDays));
        $warningThreshold = new \DateTimeImmutable(\sprintf('-%d days', $expiryDays - $warningDays));

        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.isSoldOut = true')
            ->andWhere('p.isPublished = true')
            ->andWhere('p.soldAt > :expiryThreshold')
            ->andWhere('p.soldAt <= :warningThreshold')
            ->setParameter('expiryThreshold', $expiryThreshold)
            ->setParameter('warningThreshold', $warningThreshold)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPublishedByCategory(int $categoryId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.isPublished = true')
            ->andWhere('p.category = :category')
            ->setParameter('category', $categoryId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Names already worn inside a category family, so the AI content prompt can be
     * told what not to propose.
     *
     * The scope is the family (the root category and all its children), not the
     * leaf: the type word in a name comes from the root — every child of
     * "Bracelets" yields "Jonc …" — so two pieces in sibling leaves would
     * otherwise be free to land on the very same displayed name.
     *
     * @return list<array{name: string, nameFr: string}>
     */
    public function findFamilyNames(ProductCategory $category, ?int $excludeProductId = null): array
    {
        $root = $category->getParent() ?? $category;

        $qb = $this->createQueryBuilder('p')
            ->select('p.name AS name', 'p.nameFr AS nameFr')
            ->innerJoin('p.category', 'c')
            ->where('c = :root OR c.parent = :root')
            ->setParameter('root', $root)
            ->orderBy('p.name', 'ASC');

        if ($excludeProductId !== null) {
            $qb->andWhere('p.id != :excludeProductId')
                ->setParameter('excludeProductId', $excludeProductId);
        }

        /* @var list<array{name: string, nameFr: string}> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Is this English slug already worn by another product? The two slug columns
     * are independent namespaces: the detail route matches `slug` in English and
     * `slugFr` in French, so a collision across the two is harmless.
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        return $this->slugTaken('p.slug', $slug, $excludeId);
    }

    public function slugFrExists(string $slugFr, ?int $excludeId = null): bool
    {
        return $this->slugTaken('p.slugFr', $slugFr, $excludeId);
    }

    private function slugTaken(string $field, string $value, ?int $excludeId): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where($field.' = :value')
            ->setParameter('value', $value);

        if ($excludeId !== null) {
            $qb->andWhere('p.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Resets products stuck in PendingVisuals back to Draft.
     *
     * @return int number of products reset
     */
    public function resetStuckPendingVisuals(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->update()
            ->set('p.visualStatus', ':draft')
            ->where('p.visualStatus = :pending')
            ->setParameter('draft', VisualWorkflowStatus::Draft)
            ->setParameter('pending', VisualWorkflowStatus::PendingVisuals)
            ->getQuery()
            ->execute();
    }
}
