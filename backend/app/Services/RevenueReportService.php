<?php

namespace App\Services;

use App\Models\CollectionRoute;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class RevenueReportService
{
    public function generate(Request $request): array
    {
        $filters = $request->validate([
            'basis' => ['nullable', 'in:paid_at,issued_at'],
            'dimension' => ['nullable', 'in:period,collector,route'],
            'period_unit' => ['nullable', 'in:month,quarter,year'],
            'report_type' => ['nullable', 'in:summary,detail'],
            'from_date' => ['nullable', 'date'], 'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'collector_id' => ['nullable', 'integer', 'exists:users,id'],
            'collection_route_id' => ['nullable', 'integer', 'exists:collection_routes,id'],
        ]);
        $filters += [
            'basis' => 'paid_at', 'dimension' => 'period', 'period_unit' => 'month', 'report_type' => 'summary',
            'from_date' => now()->startOfYear()->toDateString(), 'to_date' => now()->toDateString(),
            'collector_id' => null, 'collection_route_id' => null,
        ];

        $query = Payment::query()->with(['household.route:id,code,name', 'collector:id,name', 'invoice:id,payment_id,invoice_no,status,issued_at']);
        if ($filters['basis'] === 'issued_at') {
            $query->whereHas('invoice', fn ($invoice) => $invoice->where('status', 'DA_PHAT_HANH')->whereBetween('issued_at', [$filters['from_date'].' 00:00:00', $filters['to_date'].' 23:59:59']));
        } else {
            $query->whereBetween('paid_at', [$filters['from_date'].' 00:00:00', $filters['to_date'].' 23:59:59']);
        }
        if ($filters['collector_id']) $query->where('collector_id', $filters['collector_id']);
        if ($filters['collection_route_id']) $query->whereHas('household', fn ($household) => $household->where('collection_route_id', $filters['collection_route_id']));
        $payments = $query->orderBy('paid_at')->get();

        $dateOf = fn (Payment $payment) => $filters['basis'] === 'issued_at' ? $payment->invoice?->issued_at : $payment->paid_at;
        $groups = $payments->groupBy(function (Payment $payment) use ($filters, $dateOf) {
            if ($filters['dimension'] === 'collector') return 'collector-'.($payment->collector_id ?: 0);
            if ($filters['dimension'] === 'route') return 'route-'.($payment->household?->collection_route_id ?: 0);
            $date = CarbonImmutable::parse($dateOf($payment));
            return match ($filters['period_unit']) {
                'quarter' => $date->year.'-Q'.$date->quarter,
                'year' => (string) $date->year,
                default => $date->format('Y-m'),
            };
        })->map(function ($items, $key) use ($filters) {
            $first = $items->first();
            $label = match ($filters['dimension']) {
                'collector' => $first->collector?->name ?? 'Chưa xác định',
                'route' => $first->household?->route?->name ?? 'Chưa có tuyến',
                default => str_contains($key, '-Q') ? 'Quý '.substr($key, -1).'/'.substr($key, 0, 4) : (strlen($key) === 7 ? substr($key, 5, 2).'/'.substr($key, 0, 4) : $key),
            };
            return ['key' => $key, 'label' => $label, 'transactions' => $items->count(), 'households' => $items->pluck('household_id')->unique()->count(), 'amount' => round($items->sum('amount'), 2)];
        })->values();

        $details = $payments->map(fn (Payment $payment) => [
            'id' => $payment->id, 'code' => $payment->code, 'date' => $dateOf($payment)?->toIso8601String(),
            'household_code' => $payment->household?->code, 'household_name' => $payment->household?->owner_name,
            'route_name' => $payment->household?->route?->name, 'collector_name' => $payment->collector?->name,
            'from_month' => $payment->from_month?->format('Y-m'), 'to_month' => $payment->to_month?->format('Y-m'),
            'amount' => (float) $payment->amount, 'invoice_no' => $payment->invoice?->invoice_no,
        ])->values();

        return [
            'filters' => $filters,
            'summary' => ['total_revenue' => round($payments->sum('amount'), 2), 'transactions' => $payments->count(), 'households' => $payments->pluck('household_id')->unique()->count(), 'average' => $payments->count() ? round($payments->avg('amount'), 2) : 0],
            'groups' => $groups, 'details' => $details,
            'options' => [
                'collectors' => User::query()->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'payments.create'))->orderBy('name')->get(['id', 'name']),
                'routes' => CollectionRoute::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            ],
        ];
    }
}
