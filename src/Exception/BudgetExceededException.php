<?php

declare(strict_types=1);

namespace App\Exception;

final class BudgetExceededException extends \RuntimeException
{
    public function __construct(float $currentSpending, float $budget)
    {
        parent::__construct(\sprintf(
            'Monthly Gemini budget exceeded: $%.2f spent of $%.2f allowed.',
            $currentSpending,
            $budget,
        ));
    }
}
