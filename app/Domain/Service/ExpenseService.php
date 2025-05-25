<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Expense;
use App\Domain\DTOs\UserDTO;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;

class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
        private readonly LoggerInterface $logger,
    ) {}

    public function list(UserDTO $user, int $year, int $month, int $pageNumber, int $pageSize): array
    {
        $offset = ($pageNumber - 1) * $pageSize;

        $startDate = (new DateTimeImmutable())
            ->setDate($year, $month, 1)
            ->setTime(0, 0);
        
        $endDate = $startDate->modify('last day of this month')
            ->setTime(23, 59, 59);

        $criteria = [
            'user_id' => $user->id,
            'date_from' => $startDate->format('Y-m-d H:i:s'),
            'date_to' => $endDate->format('Y-m-d H:i:s'),
        ];

        return $this->expenses->findBy($criteria, $offset, $pageSize);
    }

    public function count(UserDTO $user, int $year, int $month): int
    {
        $startDate = (new DateTimeImmutable())
            ->setDate($year, $month, 1)
            ->setTime(0, 0);
        
        $endDate = $startDate->modify('last day of this month')
            ->setTime(23, 59, 59);

        $criteria = [
            'user_id' => $user->id,
            'date_from' => $startDate->format('Y-m-d H:i:s'),
            'date_to' => $endDate->format('Y-m-d H:i:s'),
        ];

        return $this->expenses->countBy($criteria);
    }

    public function create(
        UserDTO $user,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): ?Expense {
        $expense = new Expense(null, $user->id, $date, $category, (int)($amount * 100), $description, $amount, null);
        $this->expenses->save($expense);
        return $expense;
    }

    public function update(
        Expense $expense,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
        $updatedExpense = new Expense(
            id: $expense->id,
            userId: $expense->userId,
            date: $date,
            category: $category,
            amountCents: (int)($amount * 100),
            description: $description,
            amount: $amount,
            deletedAt: $expense->deletedAt,
        );

        $this->expenses->save($updatedExpense);
    }

    private function validateCsvRow(array $row, array $validCategories): ?array
    {
        if (count($row) !== 4) {
            return ['row' => $row, 'reason' => 'Invalid column count'];
        }
        
        [$dateStr, $amountStr, $description, $category] = $row;
        $category = strtolower(trim($category));
        $description = trim($description);
        
        if (empty($description)) {
            return ['row' => $row, 'reason' => 'Empty description'];
        }
        
        if (!in_array($category, $validCategories)) {
            return ['row' => $row, 'reason' => 'Unknown category'];
        }
        
        try {
            $date = new \DateTimeImmutable($dateStr);
        } catch (\Exception $e) {
            return ['row' => $row, 'reason' => 'Invalid date format'];
        }
        
        $cleanAmount = str_replace(['"', ','], '', trim($amountStr));
        if (!is_numeric($cleanAmount) || strpos($cleanAmount, '.') === false) {
            return ['row' => $row, 'reason' => 'Invalid amount format'];
        }
        
        $amount = (float)$cleanAmount;
        if ($amount <= 0) {
            return ['row' => $row, 'reason' => 'Amount must be greater than 0'];
        }
        
        return null;
    }

    private function processValidRow(array $row, UserDTO $user): Expense
    {
        [$dateStr, $amountStr, $description, $category] = $row;
        $date = new \DateTimeImmutable($dateStr);
        $cleanAmount = (float)str_replace(['"', ','], '', trim($amountStr));
        
        return new Expense(
            id: null,
            userId: $user->id,
            date: $date,
            category: strtolower(trim($category)),
            amountCents: (int)($cleanAmount * 100),
            description: trim($description),
            amount: $cleanAmount,
            deletedAt: null,
        );
    }

    private function handleImportCompletion(int $importedCount, array $skippedRows): int
    {
        if (!empty($skippedRows)) {
            foreach ($skippedRows as $skippedRow) {
                $this->logger->warning('Skipped CSV row during import', [
                    'row' => implode(',', $skippedRow['row']),
                    'reason' => $skippedRow['reason']
                ]);
            }
        }
        
        $this->logger->info('CSV import completed', [
            'imported_count' => $importedCount,
            'skipped_count' => count($skippedRows)
        ]);
        
        if ($importedCount === 0) {
            $reasons = array_unique(array_column($skippedRows, 'reason'));
            throw new \RuntimeException('No rows were imported. Reasons: ' . implode(', ', $reasons));
        }
        
        return $importedCount;
    }

    public function importFromCsv(UserDTO $user, UploadedFileInterface $csvFile): int
    {
        $skippedRows = [];
        $validExpenses = [];
        $categoryBudgets = json_decode($_ENV['CATEGORY_BUDGETS'] ?? '{}', true);
        $validCategories = array_map('strtolower', array_keys($categoryBudgets));
        $processedRows = [];

        $tmpFile = tempnam(sys_get_temp_dir(), 'csv_import_');
        $csvFile->moveTo($tmpFile);
        
        $handle = fopen($tmpFile, 'r');
        if ($handle === false) {
            unlink($tmpFile);
            throw new \RuntimeException('Failed to open CSV file');
        }
        
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $validationError = $this->validateCsvRow($row, $validCategories);
                if ($validationError !== null) {
                    $skippedRows[] = $validationError;
                    continue;
                }
                
                $rowKey = md5(implode('', $row));
                if (isset($processedRows[$rowKey])) {
                    $skippedRows[] = ['row' => $row, 'reason' => 'Duplicate entry'];
                    continue;
                }
                
                $processedRows[$rowKey] = true;
                $validExpenses[] = $this->processValidRow($row, $user);
            }

            if (count($validExpenses) === 0) {
                $reasons = array_unique(array_column($skippedRows, 'reason'));
                throw new \RuntimeException('No rows were imported. Reasons: ' . implode(', ', $reasons));
            }
            
            $importedCount = $this->expenses->importMany($validExpenses);
            return $this->handleImportCompletion($importedCount, $skippedRows);
        } finally {
            fclose($handle);
            unlink($tmpFile);
        }
    }

    public function listExpenditureYears(UserDTO $user): array
    {
        return $this->expenses->listExpenditureYears($user);
    }

    public function find(int $id): ?Expense
    {
        return $this->expenses->find($id);
    }

    public function delete(int $id): void
    {      
        $this->expenses->delete($id);
    }
}
