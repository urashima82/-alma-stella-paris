<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PageViewStat;
use App\Enum\StatDimension;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PageViewStat>
 */
class PageViewStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageViewStat::class);
    }

    /**
     * Increment the counters of one page view.
     *
     * All the day's dimensions go in a single native
     * `INSERT … ON DUPLICATE KEY UPDATE views = views + 1`: the upsert is what
     * makes the aggregate an aggregate — no read-then-write, no race between
     * concurrent requests, one statement per page view on `kernel.terminate`.
     *
     * @param list<array{StatDimension, string}> $entries dimension/value pairs
     */
    public function record(\DateTimeImmutable $day, array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $placeholders = [];
        $params = ['day' => $day->format('Y-m-d')];

        foreach ($entries as $i => [$dimension, $value]) {
            $placeholders[] = \sprintf('(:day, :dimension%1$d, :value%1$d, 1)', $i);
            $params['dimension'.$i] = $dimension->value;
            $params['value'.$i] = \mb_substr($value, 0, PageViewStat::VALUE_MAX_LENGTH);
        }

        $sql = \sprintf(
            'INSERT INTO page_view_stat (`day`, dimension, `value`, views) VALUES %s'
            .' ON DUPLICATE KEY UPDATE views = views + 1',
            \implode(', ', $placeholders),
        );

        $this->getEntityManager()->getConnection()->executeStatement($sql, $params);
    }

    /**
     * Daily view totals over the `page` dimension, oldest first. Days without
     * traffic are simply absent — filling the gaps is the caller's business.
     *
     * @return list<array{day: string, views: int}> `day` as `Y-m-d`
     */
    public function dailySeries(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $sql = <<<'SQL'
            SELECT `day` AS d, SUM(views) AS v
            FROM page_view_stat
            WHERE dimension = :dimension AND `day` BETWEEN :from AND :to
            GROUP BY `day`
            ORDER BY `day` ASC
            SQL;

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, [
            'dimension' => StatDimension::Page->value,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ]);

        return \array_map(
            static fn (array $row): array => ['day' => (string) $row['d'], 'views' => (int) $row['v']],
            $rows,
        );
    }

    /**
     * The most-viewed values of one dimension over the period, descending.
     *
     * @return list<array{value: string, views: int}>
     */
    public function top(StatDimension $dimension, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit): array
    {
        $sql = <<<'SQL'
            SELECT `value` AS val, SUM(views) AS v
            FROM page_view_stat
            WHERE dimension = :dimension AND `day` BETWEEN :from AND :to
            GROUP BY `value`
            ORDER BY v DESC
            LIMIT :limit
            SQL;

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            $sql,
            [
                'dimension' => $dimension->value,
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'limit' => \max(0, $limit),
            ],
            ['limit' => ParameterType::INTEGER],
        );

        return \array_map(
            static fn (array $row): array => ['value' => (string) $row['val'], 'views' => (int) $row['v']],
            $rows,
        );
    }

    /**
     * Total views of one dimension over the period — the honest base for a
     * percentage. {@see self::top()} returns a truncated list, so summing what
     * it gives back would compute each share against the displayed rows only
     * and overstate every one of them once the list is longer than its limit.
     */
    public function dimensionTotal(StatDimension $dimension, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $sql = <<<'SQL'
            SELECT SUM(views)
            FROM page_view_stat
            WHERE dimension = :dimension AND `day` BETWEEN :from AND :to
            SQL;

        return (int) $this->getEntityManager()->getConnection()->fetchOne($sql, [
            'dimension' => $dimension->value,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ]);
    }
}
