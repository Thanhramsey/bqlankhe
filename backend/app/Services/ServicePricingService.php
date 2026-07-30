<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServicePricePeriod;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class ServicePricingService
{
    public function periodFor(Service $service, CarbonInterface $month): ServicePricePeriod
    {
        $date = $month->copy()->startOfMonth()->toDateString();
        $period = $service->pricePeriods()
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->first();

        if (! $period) {
            throw ValidationException::withMessages(['from_month' => 'Dịch vụ '.$service->name.' chưa có mức giá áp dụng cho tháng '.$month->format('m/Y').'.']);
        }

        return $period;
    }

    public function values(Service $service, CarbonInterface $month): array
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth()->startOfDay();
        $daysInMonth = $monthStart->daysInMonth;
        $periods = $service->pricePeriods()->where('is_active', true)
            ->whereDate('effective_from', '<=', $monthEnd)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $monthStart))
            ->reorder('effective_from')->get();

        $breakdown = $periods->map(function (ServicePricePeriod $period) use ($monthStart, $monthEnd, $daysInMonth) {
            $from = $period->effective_from->greaterThan($monthStart) ? $period->effective_from : $monthStart;
            $to = $period->effective_to && $period->effective_to->lessThan($monthEnd) ? $period->effective_to : $monthEnd;
            $days = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
            $base = round((float) $period->monthly_price * $days / $daysInMonth, 2);
            $tax = round($base * (float) $period->tax_fee / 100, 2);

            return ['price_period_id' => $period->id, 'document_number' => $period->document_number,
                'document_name' => $period->document_name, 'effective_from' => $from->toDateString(),
                'effective_to' => $to->toDateString(), 'days' => $days, 'days_in_month' => $daysInMonth,
                'monthly_price' => (float) $period->monthly_price, 'base_price' => $base,
                'tax_fee_rate' => (float) $period->tax_fee, 'tax_fee_amount' => $tax,
                'amount' => round($base + $tax, 2)];
        });

        if ((int) $breakdown->sum('days') !== $daysInMonth) {
            throw ValidationException::withMessages(['from_month' => 'Dịch vụ '.$service->name.' chưa có mức giá phủ đủ các ngày trong tháng '.$month->format('m/Y').'.']);
        }

        $price = round($breakdown->sum('base_price'), 2);
        $tax = round($breakdown->sum('tax_fee_amount'), 2);
        $period = $periods->count() === 1 ? $periods->first() : null;

        return ['period' => $period, 'price' => $price, 'tax_rate' => $price > 0 ? round($tax / $price * 100, 4) : 0,
            'tax' => $tax, 'total' => round($price + $tax, 2), 'breakdown' => $breakdown->values()->all()];
    }
}
