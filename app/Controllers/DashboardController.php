<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\AlertGenerator;
use App\Domain\Service\MonthlySummaryService;
use App\Domain\Repository\ExpenseRepositoryInterface;
use App\Domain\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class DashboardController extends BaseController
{
    public function __construct(
        Twig $view,
        private readonly UserRepositoryInterface $users,
        private readonly AlertGenerator $alertGenerator,
        private readonly MonthlySummaryService $monthlySummary,
        private readonly ExpenseRepositoryInterface $expenses,
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

        $params = $request->getQueryParams();
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('m');
        
        $year = isset($params['year']) ? (int)$params['year'] : $currentYear;
        $month = isset($params['month']) ? (int)$params['month'] : $currentMonth;

        $availableYears = $this->expenses->listExpenditureYears($user);
        if (empty($availableYears)) {
            $availableYears = [$currentYear];
        }

        $alerts = $this->alertGenerator->generate($user, $year, $month);

        $totalForMonth = $this->monthlySummary->computeTotalExpenditure($user, $year, $month);
        $totalsForCategories = $this->monthlySummary->computePerCategoryTotals($user, $year, $month);
        $categoryAvgs = $this->monthlySummary->computePerCategoryAverages($user, $year, $month);

        return $this->render($response, 'dashboard.twig', [
            'alerts' => $alerts,
            'year' => $year,
            'month' => $month,
            'availableYears' => $availableYears,
            'totalForMonth' => $totalForMonth,
            'totalsForCategories' => $totalsForCategories,
            'averagesForCategories' => $categoryAvgs
        ]);
    }
}
