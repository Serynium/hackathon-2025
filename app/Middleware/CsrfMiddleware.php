<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Views\Twig;

class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Twig $view
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $params = (array)$request->getParsedBody();
            $token = $params['csrf_token'] ?? '';
            $storedToken = $_SESSION['csrf_token'] ?? '';
            
            if (!hash_equals($storedToken, $token)) {
                throw new RuntimeException('Invalid CSRF token');
            }
        }
        
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        $this->view->getEnvironment()->addGlobal('csrf_token', $_SESSION['csrf_token']);
        
        return $handler->handle($request);
    }
} 