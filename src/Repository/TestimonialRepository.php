<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Testimonial;
use App\Enum\TestimonialStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Testimonial>
 */
class TestimonialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Testimonial::class);
    }

    public function findByToken(string $token): ?Testimonial
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function findByEmail(string $email): ?Testimonial
    {
        return $this->findOneBy(['email' => \strtolower(\trim($email))]);
    }

    public function existsForEmail(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    /**
     * @return Testimonial[]
     */
    public function findApproved(int $limit = 0): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.submittedAt IS NOT NULL')
            ->setParameter('status', TestimonialStatus::Approved)
            ->orderBy('t.submittedAt', 'DESC');

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.status = :status')
            ->setParameter('status', TestimonialStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array{averageRating: float, totalCount: int}
     */
    public function getApprovedStats(): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('AVG(t.rating) as avgRating, COUNT(t.id) as total')
            ->where('t.status = :status')
            ->andWhere('t.submittedAt IS NOT NULL')
            ->setParameter('status', TestimonialStatus::Approved)
            ->getQuery()
            ->getSingleResult();

        return [
            'averageRating' => \round((float) ($result['avgRating'] ?? 0), 1),
            'totalCount' => (int) ($result['total'] ?? 0),
        ];
    }
}
