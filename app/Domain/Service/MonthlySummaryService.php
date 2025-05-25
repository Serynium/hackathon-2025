<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\DTOs\UserDTO;
use App\Domain\Repository\ExpenseRepositoryInterface;

class MonthlySummaryService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
    ) {}

    public function computeTotalExpenditure(UserDTO $user, int $year, int $month): float
    {
        $criteria = [
            'user_id' => $user->id,
            'date_from' => sprintf('%d-%02d-01', $year, $month),
            'date_to' => sprintf('%d-%02d-%d', $year, $month, date('t', strtotime("$year-$month-01")))
        ];

        return $this->expenses->sumAmounts($criteria);
    }

    public function computePerCategoryTotals(UserDTO $user, int $year, int $month): array
    {
        $criteria = [
            'user_id' => $user->id,
            'date_from' => sprintf('%d-%02d-01', $year, $month),
            'date_to' => sprintf('%d-%02d-%d', $year, $month, date('t', strtotime("$year-$month-01")))
        ];

        $totals = $this->expenses->sumAmountsByCategory($criteria);
        $total = array_sum($totals);

        $result = [];
        foreach ($totals as $category => $value) {
            $result[$category] = [
                'value' => $value,
                'percentage' => $total > 0 ? round(($value / $total) * 100) : 0
            ];
        }

        return $result;
    }

    public function computePerCategoryAverages(UserDTO $user, int $year, int $month): array
    {
        $criteria = [
            'user_id' => $user->id,
            'date_from' => sprintf('%d-%02d-01', $year, $month),
            'date_to' => sprintf('%d-%02d-%d', $year, $month, date('t', strtotime("$year-$month-01")))
        ];

        $averages = $this->expenses->averageAmountsByCategory($criteria);
        
        if (empty($averages)) {
            return [];
        }

        $maxAverage = max($averages);

        $result = [];
        foreach ($averages as $category => $value) {
            $result[$category] = [
                'value' => $value,
                'percentage' => $maxAverage > 0 ? round(($value / $maxAverage) * 100) : 0
            ];
        }

        return $result;
    }
}
