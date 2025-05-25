<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Slim\Views\Twig;

class AuthController extends BaseController
{
    public function __construct(
        Twig $view,
        private AuthService $authService,
        private LoggerInterface $logger,
    ) {
        parent::__construct($view);
    }

    public function showRegister(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/register.twig');
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';

        try {
            $this->authService->register($username, $password, $passwordConfirm);
            $this->logger->info('User registered successfully', ['username' => $username]);
            return $response->withHeader('Location', '/login')->withStatus(302);
        } catch (RuntimeException $e) {
            $this->logger->warning('Registration failed', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);

            $errors = [];
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'Username')) {
                $errors['username'] = $errorMessage;
            } elseif (str_contains($errorMessage, 'Password')) {
                if (str_contains($errorMessage, 'match')) {
                    $errors['password_confirm'] = $errorMessage;
                } else {
                    $errors['password'] = $errorMessage;
                }
            } else {
                $errors['username'] = $errorMessage;
            }

            return $this->render($response, 'auth/register.twig', [
                'errors' => $errors,
                'username' => $username
            ]);
        }
    }

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/login.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        try {
            $this->authService->attempt($username, $password);
            $this->logger->info('User logged in successfully', ['username' => $username]);
            return $response->withHeader('Location', '/')->withStatus(302);
        } catch (RuntimeException $e) {
            $this->logger->warning('Login failed', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            return $this->render($response, 'auth/login.twig', [
                'errors' => ['login' => $e->getMessage()],
                'username' => $username
            ]);
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        $_SESSION = [];
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $this->logger->info('User logged out');
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
