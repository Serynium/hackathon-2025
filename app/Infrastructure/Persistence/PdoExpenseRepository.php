<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Expense;
use App\Domain\DTOs\UserDTO;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Exception;
use PDO;

class PdoExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * @throws Exception
     */
    public function find(int $id): ?Expense
    {
        $query = 'SELECT * FROM expenses WHERE id = :id';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();
        if (false === $data) {
            return null;
        }

        return $this->createExpenseFromData($data);
    }

    public function save(Expense $expense): void
    {
        if ($expense->id === null) {
            // Insert new expense
            $query = 'INSERT INTO expenses (user_id, date, category, amount_cents, description, amount) VALUES (:user_id, :date, :category, :amount_cents, :description, :amount)';
            $statement = $this->pdo->prepare($query);
            $statement->execute([
                'user_id' => $expense->userId,
                'date' => $expense->date->format('Y-m-d'),
                'category' => $expense->category,
                'amount_cents' => $expense->amountCents,
                'description' => $expense->description,
                'amount' => $expense->amount,
            ]);
            $expense->id = (int)$this->pdo->lastInsertId();
        } else {
            // Update existing expense
            $query = 'UPDATE expenses SET date = :date, category = :category, amount_cents = :amount_cents, description = :description, amount = :amount WHERE id = :id AND user_id = :user_id';
            $statement = $this->pdo->prepare($query);
            $statement->execute([
                'id' => $expense->id,
                'user_id' => $expense->userId,
                'date' => $expense->date->format('Y-m-d'),
                'category' => $expense->category,
                'amount_cents' => $expense->amountCents,
                'description' => $expense->description,
                'amount' => $expense->amount,
            ]);
        }
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM expenses WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function findBy(array $criteria, int $offset, int $limit): array
    {
        $conditions = [];
        $params = [];

        // Build WHERE conditions based on criteria
        if (isset($criteria['user_id'])) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }

        if (isset($criteria['date_from'])) {
            $conditions[] = 'date >= :date_from';
            $params['date_from'] = $criteria['date_from'];
        }

        if (isset($criteria['date_to'])) {
            $conditions[] = 'date <= :date_to';
            $params['date_to'] = $criteria['date_to'];
        }

        // Construct the query
        $query = 'SELECT * FROM expenses';
        if (!empty($conditions)) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }
        
        // Add ordering and pagination
        $query .= ' ORDER BY date DESC LIMIT :limit OFFSET :offset';
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        // Execute query
        $statement = $this->pdo->prepare($query);
        foreach ($params as $key => $value) {
            // PDO requires explicit parameter type for LIMIT and OFFSET
            if (in_array($key, ['limit', 'offset'])) {
                $statement->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $statement->bindValue($key, $value);
            }
        }
        $statement->execute();

        // Fetch and convert to Expense entities
        $expenses = [];
        while ($row = $statement->fetch()) {
            $expenses[] = $this->createExpenseFromData($row);
        }

        return $expenses;
    }

    public function countBy(array $criteria): int
    {
        $conditions = [];
        $params = [];

        // Build WHERE conditions based on criteria
        if (isset($criteria['user_id'])) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }

        if (isset($criteria['date_from'])) {
            $conditions[] = 'date >= :date_from';
            $params['date_from'] = $criteria['date_from'];
        }

        if (isset($criteria['date_to'])) {
            $conditions[] = 'date <= :date_to';
            $params['date_to'] = $criteria['date_to'];
        }

        // Construct the count query
        $query = 'SELECT COUNT(*) FROM expenses';
        if (!empty($conditions)) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        // Execute query
        $statement = $this->pdo->prepare($query);
        $statement->execute($params);

        return (int)$statement->fetchColumn();
    }

    public function listExpenditureYears(UserDTO $user): array
    {
        $query = 'SELECT DISTINCT strftime(\'%Y\', date) as year FROM expenses WHERE user_id = :user_id ORDER BY year DESC';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['user_id' => $user->id]);
        
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function sumAmountsByCategory(array $criteria): array
    {
        $conditions = [];
        $params = [];

        // Build WHERE conditions based on criteria
        if (isset($criteria['user_id'])) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }

        if (isset($criteria['date_from'])) {
            $conditions[] = 'date >= :date_from';
            $params['date_from'] = $criteria['date_from'];
        }

        if (isset($criteria['date_to'])) {
            $conditions[] = 'date <= :date_to';
            $params['date_to'] = $criteria['date_to'];
        }

        // Construct the query
        // $query = 'SELECT category, SUM(amount_cents) / 100.0 as total FROM expenses';
        $query = 'SELECT category, SUM(amount) as total FROM expenses';
        if (!empty($conditions)) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $query .= ' GROUP BY category';

        // Execute query
        $statement = $this->pdo->prepare($query);
        $statement->execute($params);

        $results = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $results[strtolower($row['category'])] = (float)$row['total'];
        }

        return $results;
    }

    public function averageAmountsByCategory(array $criteria): array
    {
        $conditions = [];
        $params = [];

        // Build WHERE conditions based on criteria
        if (isset($criteria['user_id'])) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }

        if (isset($criteria['date_from'])) {
            $conditions[] = 'date >= :date_from';
            $params['date_from'] = $criteria['date_from'];
        }

        if (isset($criteria['date_to'])) {
            $conditions[] = 'date <= :date_to';
            $params['date_to'] = $criteria['date_to'];
        }

        // Construct the query
        // $query = 'SELECT category, AVG(amount_cents) / 100.0 as average FROM expenses';
        $query = 'SELECT category, AVG(amount) as average FROM expenses';
        if (!empty($conditions)) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $query .= ' GROUP BY category';

        // Execute query
        $statement = $this->pdo->prepare($query);
        $statement->execute($params);

        $results = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $results[strtolower($row['category'])] = (float)$row['average'];
        }

        return $results;
    }

    public function sumAmounts(array $criteria): float
    {
        $conditions = [];
        $params = [];

        // Build WHERE conditions based on criteria
        if (isset($criteria['user_id'])) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }

        if (isset($criteria['date_from'])) {
            $conditions[] = 'date >= :date_from';
            $params['date_from'] = $criteria['date_from'];
        }

        if (isset($criteria['date_to'])) {
            $conditions[] = 'date <= :date_to';
            $params['date_to'] = $criteria['date_to'];
        }

        // Construct the query
        // $query = 'SELECT SUM(amount_cents) / 100.0 as total FROM expenses';
        $query = 'SELECT SUM(amount) as total FROM expenses';
        if (!empty($conditions)) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        // Execute query
        $statement = $this->pdo->prepare($query);
        $statement->execute($params);

        return (float)$statement->fetchColumn();
    }

    /**
     * @throws Exception
     */
    private function createExpenseFromData(mixed $data): Expense
    {
        return new Expense(
            $data['id'],
            $data['user_id'],
            new DateTimeImmutable($data['date']),
            $data['category'],
            $data['amount_cents'],
            $data['description'],
            $data['amount'],
        );
    }

    public function importMany(array $expenses): int
    {
        $this->pdo->beginTransaction();
        try {
            $importedCount = 0;
            foreach ($expenses as $expense) {
                $this->save($expense);
                $importedCount++;
            }
            $this->pdo->commit();
            return $importedCount;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
