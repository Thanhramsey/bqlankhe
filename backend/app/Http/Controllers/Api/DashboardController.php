<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\Household;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $month = now()->startOfMonth();
        $data = ['households' => Household::where('is_active', true)->count(), 'revenue_month' => (float) Payment::whereBetween('paid_at', [$month, now()])->sum('amount'), 'payments_month' => Payment::whereBetween('paid_at', [$month, now()])->count(), 'outstanding_debt' => (float) Debt::where('status', 'CHUA_THU')->sum('amount'), 'revenue_chart' => Payment::selectRaw('date(paid_at) as date, sum(amount) as total')->where('paid_at', '>=', now()->subDays(29)->startOfDay())->groupBy(DB::raw('date(paid_at)'))->orderBy('date')->get()];

        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => $data]);
    }
}
