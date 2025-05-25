<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\User;

interface UserRepositoryInterface
{
    public function findByUsername(string $username): ?User;

    public function find(mixed $id): ?User;

    public function save(User $user): void;
}
