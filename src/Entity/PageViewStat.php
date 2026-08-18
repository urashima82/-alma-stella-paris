<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StatDimension;
use App\Repository\PageViewStatRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One audience counter: the number of views recorded for a single
 * `(day, dimension, value)` triple. Never a per-visit event — no IP, no
 * user-agent, no identifier, no measurement cookie is stored anywhere, so
 * there is nothing to anonymize after the fact.
 *
 * Rows are never written through the ORM: {@see PageViewStatRepository::record()}
 * upserts them with `INSERT … ON DUPLICATE KEY UPDATE views = views + 1`, which
 * is why the unique constraint on `(day, dimension, value)` is load-bearing
 * rather than merely protective.
 */
#[ORM\Entity(repositoryClass: PageViewStatRepository::class)]
#[ORM\Table(name: 'page_view_stat')]
#[ORM\UniqueConstraint(name: 'uniq_page_view_stat_key', columns: ['day', 'dimension', 'value'])]
class PageViewStat
{
    /**
     * Length of the `value` column. Values are truncated to it before binding —
     * a path or a host longer than this is pathological, and a silent DB error
     * on a measurement write would be worse than a clipped label.
     */
    public const int VALUE_MAX_LENGTH = 220;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Aggregation bucket: calendar day, no time part. */
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $day;

    #[ORM\Column(length: 20, enumType: StatDimension::class)]
    private StatDimension $dimension;

    /**
     * The measured value: request path for `page` (locale prefix included, query
     * string dropped, secret route parameters masked), referrer host for
     * `referrer`, `fr`/`en` for `locale`, `mobile`/`desktop` for `device`.
     *
     * Binary collation, because this column is half of the upsert key: the
     * server default (`utf8mb4_general_ci`) is case- AND accent-insensitive, so
     * `café.com` and `cafe.com` would fold into a single counter.
     */
    #[ORM\Column(length: self::VALUE_MAX_LENGTH, options: ['collation' => 'utf8mb4_bin'])]
    private string $value;

    #[ORM\Column]
    private int $views = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDay(): \DateTimeImmutable
    {
        return $this->day;
    }

    public function setDay(\DateTimeImmutable $day): static
    {
        $this->day = $day;

        return $this;
    }

    public function getDimension(): StatDimension
    {
        return $this->dimension;
    }

    public function setDimension(StatDimension $dimension): static
    {
        $this->dimension = $dimension;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): static
    {
        $this->views = $views;

        return $this;
    }
}
