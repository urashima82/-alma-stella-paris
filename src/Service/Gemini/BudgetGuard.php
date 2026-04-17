<?php

declare(strict_types=1);

namespace App\Service\Gemini;

use App\Entity\GeminiUsageLog;
use App\Exception\BudgetExceededException;
use App\Repository\GeminiUsageLogRepository;
use Doctrine\ORM\EntityManagerInterface;

final class BudgetGuard
{
    public function __construct(
        private readonly GeminiUsageLogRepository $usageLogRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly float $monthlyBudgetUsd,
    ) {
    }

    public function ensureBudgetAvailable(): void
    {
        $currentSpending = $this->getCurrentMonthSpending();

        if ($currentSpending >= $this->monthlyBudgetUsd) {
            throw new BudgetExceededException($currentSpending, $this->monthlyBudgetUsd);
        }
    }

    public function recordCall(float $costUsd): void
    {
        $log = new GeminiUsageLog();
        $log->setCostUsd($costUsd);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function getCurrentMonthSpending(): float
    {
        return $this->usageLogRepository->getCurrentMonthTotal();
    }
}
