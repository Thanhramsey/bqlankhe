<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CollectionRoute;
use App\Models\Household;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(private readonly ServicePricingService $pricing) {}

    public function get(array $input): array
    {
        $to = CarbonImmutable::parse($input['to_date'] ?? now())->endOfDay();
        $from = CarbonImmutable::parse($input['from_date'] ?? $to->startOfMonth())->startOfDay();
        $filters = [...$input, 'from_date' => $from->toDateString(), 'to_date' => $to->toDateString()];
        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->subDay()->endOfDay();
        $previousFrom = $previousTo->subDays($days - 1)->startOfDay();

        $householdQuery = Household::query()->where('is_active', true)->with([
            'route:id,code,name', 'route.users:id,name,avatar',
            'services' => fn ($query) => $query->where('is_active', true)->with('service:id,code,name,monthly_price,tax_fee'),
            'services.paymentMonths' => fn ($query) => $query->whereDate('month', '<=', $to->startOfMonth()),
        ]);
        $this->filterHouseholds($householdQuery, $filters);
        $households = $householdQuery->get();

        $payments = $this->paymentQuery($filters, $from, $to)->with(['household.route:id,code,name', 'household.services.service:id,name', 'collector:id,name,avatar', 'invoice', 'months'])->get();
        $previousPayments = $this->paymentQuery($filters, $previousFrom, $previousTo)->get();
        $debtItems = $this->calculateDebts($households, $to->startOfMonth());

        $householdCount = $households->count();
        $collected = $payments->pluck('household_id')->unique()->count();
        $previousCollected = $previousPayments->pluck('household_id')->unique()->count();
        $revenue = (float) $payments->sum('amount');
        $previousRevenue = (float) $previousPayments->sum('amount');
        $invoiceErrors = $payments->where('invoice.status', 'PHAT_HANH_LOI')->count();
        $previousInvoiceErrors = $previousPayments->filter(fn ($payment) => $payment->invoice?->status === 'PHAT_HANH_LOI')->count();

        return [
            'filters' => $filters,
            'updated_at' => now()->toIso8601String(),
            'kpis' => [
                $this->kpi('households', 'Tổng hộ dân', $householdCount, $householdCount, 'count'),
                $this->kpi('collected', 'Hộ đã thu', $collected, $previousCollected, 'count'),
                $this->kpi('uncollected', 'Hộ chưa thu', max(0, $householdCount - $collected), max(0, $householdCount - $previousCollected), 'count'),
                $this->kpi('revenue', 'Doanh thu', $revenue, $previousRevenue, 'currency'),
                $this->kpi('debt', 'Tổng công nợ', (float) $debtItems->sum('total_debt'), (float) $debtItems->sum('total_debt'), 'currency'),
                $this->kpi('invoice_errors', 'Hóa đơn lỗi', $invoiceErrors, $previousInvoiceErrors, 'count'),
            ],
            'revenue_chart' => $this->revenueChart($filters, $to, $input['chart_range'] ?? '12'),
            'route_performance' => $this->routePerformance($households, $payments),
            'service_distribution' => $this->serviceDistribution($households, $payments),
            'debt' => $this->debtData($debtItems),
            'top_collectors' => $this->topCollectors($filters, $input['collector_period'] ?? 'month', $input['collector_rank_by'] ?? 'revenue', $to),
            'invoice_status' => $this->invoiceStatus($payments),
            'recent_payments' => $payments->sortByDesc('paid_at')->take(12)->values()->map(fn ($payment) => $this->paymentRow($payment)),
            'alerts' => $this->alerts($debtItems, $households, $payments),
            'options' => [
                'routes' => CollectionRoute::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'collectors' => User::query()->where('is_active', true)->whereHas('collectionRoutes')->orderBy('name')->get(['id', 'name']),
                'services' => Service::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            ],
        ];
    }

    private function paymentQuery(array $filters, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $query = Payment::query()->whereBetween('paid_at', [$from, $to])->with('invoice');
        if (! empty($filters['route_id'])) $query->whereHas('household', fn ($q) => $q->where('collection_route_id', $filters['route_id']));
        if (! empty($filters['collector_id'])) $query->where('collector_id', $filters['collector_id']);
        if (! empty($filters['service_id'])) $query->whereHas('months', fn ($q) => $q->whereHas('householdService', fn ($s) => $s->where('service_id', $filters['service_id'])));
        if (! empty($filters['payment_status'])) $query->where('status', $filters['payment_status']);
        if (! empty($filters['invoice_status'])) $query->whereHas('invoice', fn ($q) => $q->where('status', $filters['invoice_status']));
        return $query;
    }

    private function filterHouseholds(Builder $query, array $filters): void
    {
        if (! empty($filters['route_id'])) $query->where('collection_route_id', $filters['route_id']);
        if (! empty($filters['collector_id'])) $query->whereHas('route.users', fn ($q) => $q->where('users.id', $filters['collector_id']));
        if (! empty($filters['service_id'])) $query->whereHas('services', fn ($q) => $q->where('service_id', $filters['service_id'])->where('is_active', true));
    }

    private function kpi(string $key, string $label, float|int $value, float|int $previous, string $format): array
    {
        $change = $previous == 0 ? ($value > 0 ? 100 : 0) : round(($value - $previous) / abs($previous) * 100, 1);
        return ['key' => $key, 'label' => $label, 'value' => $value, 'previous' => $previous, 'change_percent' => $change, 'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'), 'format' => $format];
    }

    private function revenueChart(array $filters, CarbonImmutable $to, string $range): array
    {
        $months = $range === '6' ? 6 : 12;
        $end = $to->startOfMonth();
        $start = $range === 'year' ? $end->startOfYear() : $end->subMonths($months - 1);
        $query = $this->paymentQuery($filters, $start->startOfDay(), $end->endOfMonth());
        $current = $query->get()->groupBy(fn ($payment) => $payment->paid_at->format('Y-m'))->map->sum('amount');
        $previous = $this->paymentQuery($filters, $start->subYear()->startOfDay(), $end->subYear()->endOfMonth())->get()->groupBy(fn ($payment) => $payment->paid_at->format('m'))->map->sum('amount');
        $points = collect();
        for ($month = $start; $month->lessThanOrEqualTo($end); $month = $month->addMonth()) {
            $points->push(['month' => $month->format('Y-m'), 'label' => $month->format('m/Y'), 'current' => (float) ($current[$month->format('Y-m')] ?? 0), 'previous' => (float) ($previous[$month->format('m')] ?? 0)]);
        }
        return ['range' => $range, 'points' => $points, 'max' => max(1, (float) $points->max(fn ($point) => max($point['current'], $point['previous'])))];
    }

    private function routePerformance(Collection $households, Collection $payments): Collection
    {
        return $households->groupBy(fn ($household) => $household->collection_route_id ?: 0)->map(function ($items, $routeId) use ($payments) {
            $routePayments = $payments->whereIn('household_id', $items->pluck('id'));
            $collected = $routePayments->pluck('household_id')->unique()->count(); $total = $items->count();
            return ['route_id' => $routeId, 'route_name' => $items->first()->route?->name ?? 'Chưa có tuyến', 'total_households' => $total, 'collected_households' => $collected, 'uncollected_households' => max(0, $total - $collected), 'completion_rate' => $total ? round($collected / $total * 100, 1) : 0, 'revenue' => (float) $routePayments->sum('amount')];
        })->sortBy('completion_rate')->values();
    }

    private function serviceDistribution(Collection $households, Collection $payments): Collection
    {
        $subscriptions = $households->flatMap->services;
        $serviceBySubscription = $subscriptions->pluck('service_id', 'id');
        $revenueByService = [];
        foreach ($payments as $payment) foreach ($payment->months as $month) {
            $serviceId = $serviceBySubscription[$month->household_service_id] ?? null;
            if ($serviceId) $revenueByService[$serviceId] = ($revenueByService[$serviceId] ?? 0) + (float) $month->amount;
        }
        $total = max(1, $subscriptions->pluck('household_id')->unique()->count());
        return $subscriptions->groupBy('service_id')->map(function ($items, $serviceId) use ($revenueByService, $total) {
            return ['service_id' => $serviceId, 'name' => $items->first()->service?->name ?? 'Dịch vụ khác', 'households' => $items->pluck('household_id')->unique()->count(), 'percentage' => round($items->pluck('household_id')->unique()->count() / $total * 100, 1), 'revenue' => (float) ($revenueByService[$serviceId] ?? 0)];
        })->sortByDesc('households')->values();
    }

    private function calculateDebts(Collection $households, CarbonImmutable $to): Collection
    {
        return $households->map(function ($household) use ($to) {
            $unpaid = collect(); $latestPaid = null;
            foreach ($household->services as $subscription) {
                $start = CarbonImmutable::parse($subscription->started_at)->startOfMonth();
                $subscriptionEnd = $subscription->ended_at ? CarbonImmutable::parse($subscription->ended_at)->startOfMonth() : $to;
                $end = $subscriptionEnd->lessThan($to) ? $subscriptionEnd : $to;
                if ($start->greaterThan($end)) continue;
                $paid = $subscription->paymentMonths->pluck('month')->map(fn ($month) => CarbonImmutable::parse($month)->format('Y-m'))->flip();
                $latest = $subscription->paymentMonths->max('month'); if ($latest && (! $latestPaid || CarbonImmutable::parse($latest)->greaterThan($latestPaid))) $latestPaid = CarbonImmutable::parse($latest);
                for ($month = $start; $month->lessThanOrEqualTo($end); $month = $month->addMonth()) if (! $paid->has($month->format('Y-m'))) $unpaid->push(['month' => $month, 'amount' => $this->pricing->values($subscription->service, $month)['total']]);
            }
            if ($unpaid->isEmpty()) return null;
            $oldest = $unpaid->sortBy(fn ($item) => $item['month']->timestamp)->first()['month'];
            return ['household_id' => $household->id, 'code' => $household->code, 'owner_name' => $household->owner_name, 'route_name' => $household->route?->name ?? 'Chưa có tuyến', 'collector_name' => $household->route?->users?->first()?->name ?? 'Chưa phân công', 'last_paid_month' => $latestPaid?->format('Y-m'), 'debt_months' => $unpaid->count(), 'total_debt' => round($unpaid->sum('amount'), 2), 'oldest_month' => $oldest->format('Y-m')];
        })->filter()->values();
    }

    private function debtData(Collection $items): array
    {
        $buckets = [['key' => '1_3', 'label' => 'Nợ 1–3 tháng', 'min' => 1, 'max' => 3], ['key' => '4_6', 'label' => 'Nợ 4–6 tháng', 'min' => 4, 'max' => 6], ['key' => '7_12', 'label' => 'Nợ 7–12 tháng', 'min' => 7, 'max' => 12], ['key' => 'over_12', 'label' => 'Nợ trên 12 tháng', 'min' => 13, 'max' => PHP_INT_MAX]];
        return ['buckets' => collect($buckets)->map(fn ($bucket) => [...$bucket, 'households' => $items->whereBetween('debt_months', [$bucket['min'], $bucket['max']])->count(), 'amount' => (float) $items->whereBetween('debt_months', [$bucket['min'], $bucket['max']])->sum('total_debt')]), 'high_debts' => $items->sortByDesc('total_debt')->take(10)->values(), 'total' => (float) $items->sum('total_debt')];
    }

    private function topCollectors(array $filters, string $period, string $rankBy, CarbonImmutable $to): Collection
    {
        $from = match ($period) { 'today' => $to->startOfDay(), 'quarter' => $to->startOfQuarter(), default => $to->startOfMonth() };
        $payments = $this->paymentQuery($filters, $from, $to)->get();
        $collectorIds = $payments->pluck('collector_id')->filter()->unique();
        $collectors = User::query()->with('collectionRoutes:id')->whereIn('id', $collectorIds)->get()->keyBy('id');
        $householdsByRoute = Household::query()->where('is_active', true)
            ->when(! empty($filters['service_id']), fn ($query) => $query->whereHas('services', fn ($service) => $service->where('service_id', $filters['service_id'])->where('is_active', true)))
            ->selectRaw('collection_route_id, count(*) as total')->groupBy('collection_route_id')->pluck('total', 'collection_route_id');
        return $payments->groupBy('collector_id')->map(function ($items, $collectorId) use ($collectors, $householdsByRoute) {
            $collector = $collectors[$collectorId] ?? null; $households = $items->pluck('household_id')->unique()->count();
            $assigned = $collector?->collectionRoutes->sum(fn ($route) => (int) ($householdsByRoute[$route->id] ?? 0)) ?? 0;
            return ['id' => $collector?->id, 'name' => $collector?->name ?? 'Chưa xác định', 'avatar_url' => $collector?->avatar ? asset('storage/'.$collector->avatar) : null, 'households' => $households, 'revenue' => (float) $items->sum('amount'), 'transactions' => $items->count(), 'completion_rate' => $assigned ? round($households / $assigned * 100, 1) : 0];
        })->sortByDesc($rankBy === 'completion' ? 'completion_rate' : 'revenue')->take(8)->values()->map(fn ($item, $index) => [...$item, 'rank' => $index + 1]);
    }

    private function invoiceStatus(Collection $payments): array
    {
        $definitions = ['CHO_PHAT_HANH' => 'Chưa phát hành', 'DANG_PHAT_HANH' => 'Đang xử lý', 'DA_PHAT_HANH' => 'Đã phát hành', 'PHAT_HANH_LOI' => 'Phát hành lỗi', 'DA_HUY' => 'Đã hủy'];
        $statuses = collect($definitions)->map(fn ($label, $status) => ['status' => $status, 'label' => $label, 'count' => $payments->filter(fn ($payment) => $payment->invoice?->status === $status)->count(), 'amount' => (float) $payments->filter(fn ($payment) => $payment->invoice?->status === $status)->sum('amount')])->values();
        $failures = $payments->filter(fn ($payment) => $payment->invoice?->status === 'PHAT_HANH_LOI')->map(function ($payment) {
            $response = $payment->invoice?->provider_response ?? []; $error = $response['message'] ?? 'Không xác định';
            return ['invoice_id' => $payment->invoice?->id, 'payment_id' => $payment->id, 'household_name' => $payment->household?->owner_name, 'payment_code' => $payment->code, 'amount' => (float) $payment->amount, 'failed_at' => $payment->invoice?->updated_at?->toIso8601String(), 'error' => $error, 'retry_count' => AuditLog::where('action', 'PUBLISH_INVOICE_FAILED')->where('entity_id', $payment->invoice?->id)->count()];
        })->values();
        return ['statuses' => $statuses, 'failures' => $failures];
    }

    private function paymentRow(Payment $payment): array
    {
        return ['id' => $payment->id, 'paid_at' => $payment->paid_at?->toIso8601String(), 'code' => $payment->code, 'household_name' => $payment->household?->owner_name, 'route_name' => $payment->household?->route?->name, 'collector_name' => $payment->collector?->name, 'period' => $payment->from_month?->format('Y-m').' – '.$payment->to_month?->format('Y-m'), 'service_name' => $payment->household?->services?->first()?->service?->name, 'amount' => (float) $payment->amount, 'invoice_status' => $payment->invoice?->status];
    }

    private function alerts(Collection $debts, Collection $households, Collection $payments): Collection
    {
        $invoiceErrors = $payments->filter(fn ($payment) => $payment->invoice?->status === 'PHAT_HANH_LOI')->count();
        $unsynced = $payments->filter(fn ($payment) => ! $payment->invoice)->count();
        $overSix = $debts->where('debt_months', '>', 6)->count();
        $unpriced = Service::query()->where('is_active', true)->where('monthly_price', '<=', 0)->count();
        $unassigned = User::query()->where('is_active', true)->whereHas('roles.permissions', fn ($q) => $q->where('code', 'payments.create'))->whereDoesntHave('collectionRoutes')->count();
        $average = (float) $payments->avg('amount'); $abnormal = $average > 0 ? $payments->where('amount', '>', $average * 3)->count() : 0;
        $lowRoutes = $households->groupBy('collection_route_id')->filter(function ($items) use ($payments) {
            if ($items->isEmpty()) return false;
            $collected = $payments->whereIn('household_id', $items->pluck('id'))->pluck('household_id')->unique()->count();
            return $collected / $items->count() * 100 < 70;
        })->count();
        return collect([
            ['key' => 'invoice_errors', 'title' => 'Hóa đơn phát hành lỗi', 'count' => $invoiceErrors, 'severity' => 'critical', 'path' => '/invoices'],
            ['key' => 'unsynced', 'title' => 'Phiếu thu chưa có hóa đơn', 'count' => $unsynced, 'severity' => 'warning', 'path' => '/invoices'],
            ['key' => 'long_debt', 'title' => 'Hộ nợ trên 6 tháng', 'count' => $overSix, 'severity' => 'critical', 'path' => '/debts'],
            ['key' => 'low_routes', 'title' => 'Tuyến có tỷ lệ thu thấp', 'count' => $lowRoutes, 'severity' => 'warning', 'path' => '/households'],
            ['key' => 'abnormal', 'title' => 'Giao dịch giá trị bất thường', 'count' => $abnormal, 'severity' => 'warning', 'path' => '/payments'],
            ['key' => 'unpriced', 'title' => 'Dịch vụ chưa cấu hình giá', 'count' => $unpriced, 'severity' => 'critical', 'path' => '/services'],
            ['key' => 'unassigned', 'title' => 'Nhân viên chưa được phân tuyến', 'count' => $unassigned, 'severity' => 'info', 'path' => '/users'],
        ])->where('count', '>', 0)->values();
    }
}
