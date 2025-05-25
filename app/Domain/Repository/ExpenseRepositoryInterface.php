<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Expense;
use App\Domain\DTOs\UserDTO;

interface ExpenseRepositoryInterface
{
    public function save(Expense $expense): void;

    public function delete(int $id): void;

    public function find(int $id): ?Expense;

    public function findBy(array $criteria, int $from, int $limit): array;

    public function countBy(array $criteria): int;

    public function listExpenditureYears(UserDTO $user): array;

    public function sumAmountsByCategory(array $criteria): array;

    public function averageAmountsByCategory(array $criteria): array;

    public function sumAmounts(array $criteria): float;
}
