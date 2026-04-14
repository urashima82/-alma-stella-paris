<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\WishlistItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WishlistItem>
 */
class WishlistItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WishlistItem::class);
    }

    /**
     * @return WishlistItem[]
     */
    public function findVisibleByCustomer(Customer $customer): array
    {
        return $this->createQueryBuilder('wi')
            ->join('wi.product', 'p')
            ->where('wi.customer = :customer')
            ->andWhere('p.isSoldOut = false')
            ->andWhere('p.isPublished = true')
            ->setParameter('customer', $customer)
            ->orderBy('wi.addedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countSoldByCustomer(Customer $customer): int
    {
        return (int) $this->createQueryBuilder('wi')
            ->select('COUNT(wi.id)')
            ->join('wi.product', 'p')
            ->where('wi.customer = :customer')
            ->andWhere('p.isSoldOut = true OR p.isPublished = false')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByCustomerAndProduct(Customer $customer, Product $product): ?WishlistItem
    {
        return $this->findOneBy([
            'customer' => $customer,
            'product' => $product,
        ]);
    }

    /**
     * @return int[]
     */
    public function getProductIdsByCustomer(Customer $customer): array
    {
        $rows = $this->createQueryBuilder('wi')
            ->select('IDENTITY(wi.product) AS productId')
            ->join('wi.product', 'p')
            ->where('wi.customer = :customer')
            ->andWhere('p.isSoldOut = false')
            ->andWhere('p.isPublished = true')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleColumnResult();

        return \array_map('\intval', $rows);
    }

    public function deleteSoldAndUnpublished(Customer $customer): int
    {
        return (int) $this->createQueryBuilder('wi')
            ->delete()
            ->where('wi.customer = :customer')
            ->andWhere('wi.id IN (
                SELECT wi2.id FROM App\Entity\WishlistItem wi2
                JOIN wi2.product p
                WHERE wi2.customer = :customer
                AND (p.isSoldOut = true OR p.isPublished = false)
            )')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->execute();
    }
}
