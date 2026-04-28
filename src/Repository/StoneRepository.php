<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Stone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stone>
 */
class StoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stone::class);
    }

    /**
     * @return Stone[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.nameFr', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug, string $locale): ?Stone
    {
        $field = $locale === 'fr' ? 's.slugFr' : 's.slug';

        return $this->createQueryBuilder('s')
            ->where(\sprintf('%s = :slug', $field))
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<string> $slugs
     *
     * @return list<Stone>
     */
    public function findBySlugs(array $slugs, string $locale): array
    {
        if ($slugs === []) {
            return [];
        }

        $field = $locale === 'fr' ? 's.slugFr' : 's.slug';

        /* @var list<Stone> */
        return $this->createQueryBuilder('s')
            ->where(\sprintf('%s IN (:slugs)', $field))
            ->setParameter('slugs', $slugs)
            ->orderBy('s.nameFr', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Stone[]
     */
    public function findWithAvailableProducts(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.products', 'p')
            ->addSelect('p')
            ->orderBy('s.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
