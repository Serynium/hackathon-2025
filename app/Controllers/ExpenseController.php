<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\ExpenseService;
use App\Domain\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ExpenseController extends BaseController
{
    private const PAGE_SIZE = 20;

    public function __construct(
        Twig $view,
        private readonly ExpenseService $expenseService,
        private readonly AuthService $authService,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserSession();
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        $now = new \DateTimeImmutable();
        $year = (int)($request->getQueryParams()['year'] ?? $now->format('Y'));
        $month = (int)($request->getQueryParams()['month'] ?? $now->format('n'));

        $total = $this->expenseService->count($user, $year, $month);
        $availableYears = $this->expenseService->listExpenditureYears($user);
        
        if (empty($availableYears)) {
            $availableYears = [$now->format('Y')];
        }
        if (!in_array($year, $availableYears)) {
            $year = reset($availableYears);
        }

        $page = (int)($request->getQueryParams()['page'] ?? 1);

        $expenses = $this->expenseService->list($user, $year, $month, $page, self::PAGE_SIZE);
        
        $totalPages = (int)ceil($total / self::PAGE_SIZE);

        $page = max(1, min($page, $totalPages ?: 1));

        $queryParams = $request->getQueryParams();
        $success = $queryParams['success'] ?? null;
        $error = $queryParams['error'] ?? null;
        $importCount = $queryParams['count'] ?? null;

        return $this->render($response, 'expenses/index.twig', [
            'expenses' => $expenses,
            'total' => $total,
            'page' => $page,
            'pageSize' => self::PAGE_SIZE,
            'totalPages' => $totalPages,
            'year' => $year,
            'month' => $month,
            'availableYears' => $availableYears,
            'success' => $success,
            'error' => $error,
            'importCount' => $importCount
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $categoryBudgets = json_decode($_ENV['CATEGORY_BUDGETS'] ?? '{}', true);
        $categories = array_keys($categoryBudgets);

        $defaultDate = (new \DateTimeImmutable())->format('Y-m-d');

        return $this->render($response, 'expenses/create.twig', [
            'categories' => $categories,
            'defaultDate' => $defaultDate
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserSession();
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $validationResult = $this->validateExpenseData($request->getParsedBody(), new \DateTimeImmutable());
        
        if (!empty($validationResult['errors'])) {
            return $this->render($response, 'expenses/create.twig', [
                'errors' => $validationResult['errors'],
                'categories' => array_keys($validationResult['data']['categoryBudgets']),
                'defaultDate' => (new \DateTimeImmutable())->format('Y-m-d'),
                'oldInput' => [
                    'date' => $validationResult['data']['date'],
                    'category' => $validationResult['data']['category'],
                    'amount' => $validationResult['data']['amount'],
                    'description' => $validationResult['data']['description']
                ]
            ]);
        }

        try {
            $data = $validationResult['data'];
            $this->expenseService->create(
                user: $user,
                amount: $data['amount'],
                description: $data['description'],
                date: $data['expenseDate'],
                category: $data['category']
            );

            return $response->withHeader('Location', '/expenses')->withStatus(302);
        } catch (\Exception $e) {
            return $this->render($response, 'expenses/create.twig', [
                'errors' => ['general' => 'Failed to create expense: ' . $e->getMessage()],
                'categories' => array_keys($validationResult['data']['categoryBudgets']),
                'defaultDate' => (new \DateTimeImmutable())->format('Y-m-d'),
                'oldInput' => [
                    'date' => $validationResult['data']['date'],
                    'category' => $validationResult['data']['category'],
                    'amount' => $validationResult['data']['amount'],
                    'description' => $validationResult['data']['description']
                ]
            ]);
        }
    }

    private function validateExpenseData(array $data, \DateTimeImmutable $today): array
    {
        $errors = [];
        $date = $data['date'] ?? '';
        $category = strtolower($data['category'] ?? '');
        $amount = (float)($data['amount'] ?? 0);
        $description = trim($data['description'] ?? '');
        $expenseDate = null;

        try {
            $expenseDate = new \DateTimeImmutable($date);
            if ($expenseDate > $today) {
                $errors['date'] = 'Date cannot be in the future';
                $expenseDate = null;
            }
        } catch (\Exception $e) {
            $errors['date'] = 'Invalid date format';
        }

        $categoryBudgets = json_decode($_ENV['CATEGORY_BUDGETS'] ?? '{}', true);
        $validCategories = array_map('strtolower', array_keys($categoryBudgets));
        if (!in_array($category, $validCategories)) {
            $errors['category'] = 'Invalid category selected';
        }

        if ($amount <= 0) {
            $errors['amount'] = 'Amount must be greater than 0';
        }

        if (empty($description)) {
            $errors['description'] = 'Description cannot be empty';
        }

        return [
            'errors' => $errors,
            'data' => [
                'date' => $date,
                'expenseDate' => $expenseDate,
                'category' => $category,
                'amount' => $amount,
                'description' => $description,
                'categoryBudgets' => $categoryBudgets
            ]
        ];
    }

    public function edit(Request $request, Response $response, array $routeParams): Response
    {
        $user = $this->authService->getUserSession();
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $expenseId = (int)($routeParams['id'] ?? 0);
        if ($expenseId <= 0) {
            return $response->withStatus(404);
        }

        $expense = $this->expenseService->find($expenseId);
        if (!$expense) {
            return $response->withStatus(404);
        }

        if ($expense->userId !== $user->id) {
            return $response->withStatus(403);
        }

        $categoryBudgets = json_decode($_ENV['CATEGORY_BUDGETS'] ?? '{}', true);
        $categories = array_keys($categoryBudgets);

        // $amount = $expense->amountCents / 100;
        $amount = $expense->amount;

        return $this->render($response, 'expenses/edit.twig', [
            'expense' => [
                'id' => $expense->id,
                'date' => $expense->date->format('Y-m-d'),
                'category' => $expense->category,
                'amount' => $amount,
                'description' => $expense->description
            ],
            'categories' => $categories
        ]);
    }

    private function renderEditForm(Response $response, array $errors, array $data, int $expenseId): Response
    {
        return $this->render($response, 'expenses/edit.twig', [
            'errors' => $errors,
            'categories' => array_keys($data['categoryBudgets']),
            'expense' => [
                'id' => $expenseId,
                'date' => $data['date'],
                'category' => $data['category'],
                'amount' => $data['amount'],
                'description' => $data['description']
            ],
            'oldInput' => [
                'date' => $data['date'],
                'category' => $data['category'],
                'amount' => $data['amount'],
                'description' => $data['description']
            ]
        ]);
    }

    public function update(Request $request, Response $response, array $routeParams): Response
    {
        $user = $this->authService->getUserSession();
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $expenseId = (int)($routeParams['id'] ?? 0);
        if ($expenseId <= 0) {
            return $response->withStatus(404);
        }

        $expense = $this->expenseService->find($expenseId);
        if (!$expense || $expense->userId !== $user->id) {
            return $response->withStatus($expense ? 403 : 404);
        }

        $validationResult = $this->validateExpenseData($request->getParsedBody(), new \DateTimeImmutable());
        if (!empty($validationResult['errors'])) {
            return $this->renderEditForm($response, $validationResult['errors'], $validationResult['data'], $expenseId);
        }

        try {
            $data = $validationResult['data'];
            $this->expenseService->update(
                expense: $expense,
                amount: $data['amount'],
                description: $data['description'],
                date: $data['expenseDate'],
                category: $data['category']
            );

            return $response->withHeader('Location', '/expenses')->withStatus(302);
        } catch (\Exception $e) {
            return $this->renderEditForm(
                $response,
                ['general' => 'Failed to update expense: ' . $e->getMessage()],
                $validationResult['data'],
                $expenseId
            );
        }
    }

    public function destroy(Request $request, Response $response, array $routeParams): Response
    {
        $user = $this->authService->getUserSession();
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $expenseId = (int)($routeParams['id'] ?? 0);
        if ($expenseId <= 0) {
            return $response->withHeader('Location', '/expenses?error=delete')->withStatus(302);
        }

        $expense = $this->expenseService->find($expenseId);
        if (!$expense) {
            return $response->withHeader('Location', '/expenses?error=delete')->withStatus(302);
        }

        if ($expense->userId !== $user->id) {
            return $response->withStatus(403);
        }

        try {
            $this->expenseService->delete($expenseId);
            return $response->withHeader('Location', '/expenses?success=delete')->withStatus(302);
        } catch (\Exception $e) {
            return $response->withHeader('Location', '/expenses?error=delete')->withStatus(302);
        }
    }

    public function import(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserSession();
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $uploadedFiles = $request->getUploadedFiles();
        $csvFile = $uploadedFiles['csv'] ?? null;

        if (!$csvFile || $csvFile->getError() !== UPLOAD_ERR_OK) {
            return $response->withHeader('Location', '/expenses?error=upload')->withStatus(302);
        }

        try {
            $importedCount = $this->expenseService->importFromCsv($user, $csvFile);
            return $response->withHeader('Location', "/expenses?success=import&count={$importedCount}")->withStatus(302);
        } catch (\Exception $e) {
            return $response->withHeader('Location', '/expenses?error=import')->withStatus(302);
        }
    }
}
