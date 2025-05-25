<?php

declare(strict_types=1);

namespace App\Domain\DTOs;

final class UserDTO
{
    public function __construct(
        public ?int $id,
        public string $username,
    ) {}
} 