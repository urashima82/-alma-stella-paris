<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\SourcePhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SourcePhoto>
 */
class SourcePhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SourcePhoto::class);
    }

    /**
     * Next free position for a product, derived from MAX(position) rather than
     * COUNT(). Counting breaks as soon as a photo in the middle is deleted:
     * with positions 1/2/3, removing #2 leaves COUNT() = 2, so the next upload
     * would reuse position 3 — duplicating the row and, because the storage
     * path is built from the position, overwriting the file of photo #3.
     */
    public function nextPositionFor(Product $product): int
    {
        $max = $this->createQueryBuilder('s')
            ->select('COALESCE(MAX(s.position), 0)')
            ->andWhere('s.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;
    }
}
