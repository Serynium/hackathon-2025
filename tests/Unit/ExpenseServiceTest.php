<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use App\Domain\Service\ExpenseService;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use App\Domain\DTOs\UserDTO;

class ExpenseServiceTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testCreateExpense(): void
    {
        $repo = $this->createMock(ExpenseRepositoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $repo->expects($this->once())->method('save');
        $user = new User(1, 'test', 'hash', new DateTimeImmutable());

        $service = new ExpenseService($repo, $logger);
        $date = new DateTimeImmutable('2025-01-02');

        $userDTO = new UserDTO($user->id, $user->username);
        $expense = $service->create($userDTO, 12.3, 'Meat and dairy', $date, 'groceries');

        $this->assertSame($date, $expense->date);
        $this->assertSame(1, $expense->userId);
        $this->assertSame(1230, $expense->amountCents);
        $this->assertSame(12.3, $expense->amount);
    }
}
