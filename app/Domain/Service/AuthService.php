<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\DTOs\UserDTO;
use App\Domain\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use RuntimeException;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function register(string $username, string $password, string $passwordConfirm): User
    {
        if (strlen($username) < 4) {
            throw new RuntimeException('Username must be at least 4 characters long');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters long');
        }
        if (!preg_match('/\d/', $password)) {
            throw new RuntimeException('Password must contain at least one number');
        }

        if ($password !== $passwordConfirm) {
            throw new RuntimeException('Passwords do not match');
        }

        if ($this->users->findByUsername($username) !== null) {
            throw new RuntimeException('Username is already taken');
        }

        $user = new User(
            null,
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            new DateTimeImmutable()
        );

        $this->users->save($user);

        return $user;
    }

    public function attempt(string $username, string $password): bool
    {
        if (empty($username)) {
            throw new RuntimeException('Username is required');
        }

        if (empty($password)) {
            throw new RuntimeException('Password is required');
        }

        $user = $this->users->findByUsername($username);
        
        if ($user === null) {
            throw new RuntimeException('No account found with this username');
        }

        if (!password_verify($password, $user->passwordHash)) {
            throw new RuntimeException('Incorrect password');
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;

        return true;
    }

    public function getUserSession(): ?UserDTO
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        $user = $this->users->find($_SESSION['user_id']);
        if (!$user) {
            return null;
        }

        return new UserDTO($user->id, $user->username);
    }
}
