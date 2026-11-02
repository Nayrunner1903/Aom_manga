<?php

namespace App\Http\Controllers\Api;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SummaryController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['month' => ['sometimes', 'date_format:Y-m', 'after_or_equal:1900-01', 'before_or_equal:9999-12']]);
        $month = CarbonImmutable::parse(($validated['month'] ?? now('Asia/Bangkok')->format('Y-m')).'-01', 'Asia/Bangkok');
        $rows = $this->user($request)->transactions()->select('type')->selectRaw('SUM(amount_satang) AS total')
            ->selectRaw('SUM(CASE WHEN date(transaction_date) BETWEEN ? AND ? THEN amount_satang ELSE 0 END) AS monthly', [
                $month->toDateString(), $month->endOfMonth()->toDateString(),
            ])->groupBy('type')->toBase()->get()->keyBy('type');
        $income = (int) ($rows->get('income')->total ?? 0);
        $expense = (int) ($rows->get('expense')->total ?? 0);
        $monthlyIncome = (int) ($rows->get('income')->monthly ?? 0);
        $monthlyExpense = (int) ($rows->get('expense')->monthly ?? 0);

        return response()->json(['data' => [
            'currency' => 'THB', 'month' => $month->format('Y-m'),
            'income_satang' => $income, 'expense_satang' => $expense, 'balance_satang' => $income - $expense,
            'monthly_income_satang' => $monthlyIncome, 'monthly_expense_satang' => $monthlyExpense,
            'monthly_balance_satang' => $monthlyIncome - $monthlyExpense,
        ]]);
    }
}
