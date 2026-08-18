<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\StatDimension;
use App\Repository\PageViewStatRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Audience dashboard: aggregate page-view counters rendered inside the
 * EasyAdmin layout. As a non-CRUD controller we are outside the
 * AdminRouterSubscriber flow, so the AdminContext (sidebar, i18n, asset paths)
 * is built and attached manually — same pattern as ProductWizardController.
 */
final class StatsController extends AbstractController
{
    /** Offered aggregation windows, in days. */
    private const array PERIODS = [7, 30, 90, 365];

    private const int DEFAULT_PERIOD = 30;

    public function __construct(
        private readonly PageViewStatRepository $stats,
        private readonly AdminContextFactory $adminContextFactory,
        private readonly DashboardController $dashboardController,
    ) {
    }

    #[Route('/admin/statistiques', name: 'admin_stats', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->attachAdminContext($request);

        $period = $this->period($request);
        $to = new \DateTimeImmutable('today');
        $from = $to->modify(\sprintf('-%d days', $period - 1));

        $series = $this->fillGaps($this->stats->dailySeries($from, $to), $from, $to);
        $total = \array_sum(\array_column($series, 'views'));

        return $this->render('admin/stats/index.html.twig', [
            'periods' => self::PERIODS,
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'series' => $series,
            'peak' => \max([0, ...\array_column($series, 'views')]),
            'total' => $total,
            'topPages' => $this->stats->top(StatDimension::Page, $from, $to, 20),
            'topReferrers' => $this->stats->top(StatDimension::Referrer, $from, $to, 20),
            'locales' => $this->stats->top(StatDimension::Locale, $from, $to, 10),
            'devices' => $this->stats->top(StatDimension::Device, $from, $to, 10),
            // Percentage bases: the full dimension total, never the sum of the
            // truncated top-N. `total` is already that for the page dimension.
            'referrersTotal' => $this->stats->dimensionTotal(StatDimension::Referrer, $from, $to),
            'localesTotal' => $this->stats->dimensionTotal(StatDimension::Locale, $from, $to),
            'devicesTotal' => $this->stats->dimensionTotal(StatDimension::Device, $from, $to),
        ]);
    }

    private function attachAdminContext(Request $request): void
    {
        if ($request->attributes->get(EA::CONTEXT_REQUEST_ATTRIBUTE) !== null) {
            return;
        }

        $context = $this->adminContextFactory->create($request, $this->dashboardController, null);
        $request->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $context);
    }

    /**
     * The selected period in days, re-validated against the offered list: a
     * bookmarked or hand-edited `period` must fall back, not produce a 400.
     */
    private function period(Request $request): int
    {
        $raw = $request->query->all()['period'] ?? null;
        $period = \is_numeric($raw) ? (int) $raw : self::DEFAULT_PERIOD;

        return \in_array($period, self::PERIODS, true) ? $period : self::DEFAULT_PERIOD;
    }

    /**
     * The repository omits days without traffic; a chart needs a continuous
     * axis, so the holes are filled with zeros here.
     *
     * @param list<array{day: string, views: int}> $rows
     *
     * @return list<array{day: string, views: int}>
     */
    private function fillGaps(array $rows, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $byDay = \array_column($rows, 'views', 'day');

        $series = [];
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $series[] = ['day' => $key, 'views' => (int) ($byDay[$key] ?? 0)];
        }

        return $series;
    }
}
