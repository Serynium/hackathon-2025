<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\DTOs\UserDTO;
use App\Domain\Repository\ExpenseRepositoryInterface;

class AlertGenerator
{
    private array $categoryBudgets;

    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses
    ) {
        $rawBudgets = json_decode($_ENV['CATEGORY_BUDGETS'] ?? '{}', true);
        $this->categoryBudgets = array_combine(
            array_map('strtolower', array_keys($rawBudgets)),
            array_values($rawBudgets)
        );
    }

    public function generate(UserDTO $user, int $year, int $month): array
    {
        $alerts = [];
        
        $criteria = [
            'user_id' => $user->id,
            'date_from' => sprintf('%d-%02d-01', $year, $month),
            'date_to' => sprintf('%d-%02d-%d', $year, $month, date('t', strtotime("$year-$month-01")))
        ];
        
        $categoryTotals = $this->expenses->sumAmountsByCategory($criteria);
        
        foreach ($this->categoryBudgets as $categoryLower => $budget) {
            if (!isset($categoryTotals[$categoryLower])) {
                continue;
            }
            
            $total = $categoryTotals[$categoryLower];
            if ($total > $budget) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => sprintf(
                        '%s budget exceeded by %.2f €',
                        ucfirst($categoryLower),
                        $total - $budget
                    )
                ];
            }
        }
        
        if (empty($alerts)) {
            $alerts[] = [
                'type' => 'success',
                'message' => 'Looking good! You\'re within budget for this month.'
            ];
        }
        
        return $alerts;
    }
}
