<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Debt;
use App\Models\Household;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMonth;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function collectMany(array $data, int $collectorId, ?string $ip = null): Collection
    {
        return DB::transaction(function () use ($data, $collectorId, $ip) {
            return collect($data['household_ids'])->map(function (int $householdId) use ($data, $collectorId, $ip) {
                $item = $data;
                unset($item['household_ids']);
                $item['household_id'] = $householdId;

                return $this->collect($item, $collectorId, $ip);
            });
        });
    }

    public function collect(array $data, int $collectorId, ?string $ip = null): Payment
    {
        $from = CarbonImmutable::createFromFormat('Y-m', $data['from_month'])->startOfMonth();
        $to = CarbonImmutable::createFromFormat('Y-m', $data['to_month'])->startOfMonth();

        return DB::transaction(function () use ($data, $collectorId, $ip, $from, $to) {
            $household = Household::query()->lockForUpdate()->with(['services' => fn ($q) => $q->where('is_active', true)->with('service')])->findOrFail($data['household_id']);
            if ($household->services->isEmpty()) {
                throw ValidationException::withMessages(['household_id' => 'Hộ dân chưa đăng ký dịch vụ đang hoạt động.']);
            }
            $items = [];
            $total = 0;
            for ($month = $from; $month->lte($to); $month = $month->addMonth()) {
                foreach ($household->services as $subscription) {
                    if ($subscription->started_at->startOfMonth()->gt($month) || ($subscription->ended_at && $subscription->ended_at->startOfMonth()->lt($month))) {
                        continue;
                    }
                    $duplicate = PaymentMonth::where('household_service_id', $subscription->id)->whereDate('month', $month)->exists();
                    if ($duplicate) {
                        throw ValidationException::withMessages(['from_month' => 'Khoảng tháng đã có kỳ thu '.$month->format('m/Y').' cho dịch vụ '.$subscription->service->name.'.']);
                    }
                    $monthlyPrice = (float) $subscription->service->monthly_price;
                    $taxFeeRate = (float) $subscription->service->tax_fee;
                    $amountWithTaxFee = round($monthlyPrice * (1 + $taxFeeRate / 100), 2);
                    $items[] = ['household_service_id' => $subscription->id, 'month' => $month->toDateString(), 'amount' => $amountWithTaxFee];
                    $total += $amountWithTaxFee;
                }
            }
            if (! $items) {
                throw ValidationException::withMessages(['from_month' => 'Không có dịch vụ phát sinh trong khoảng thời gian đã chọn.']);
            }
            $payment = Payment::create(['code' => 'PT'.now()->format('YmdHis').random_int(100, 999), 'household_id' => $household->id, 'collector_id' => $collectorId, 'from_month' => $from, 'to_month' => $to, 'amount' => $total, 'status' => 'DA_THU', 'payment_method' => $data['payment_method'] ?? 'TIEN_MAT', 'paid_at' => now(), 'note' => $data['note'] ?? null]);
            foreach ($items as $item) {
                $payment->months()->create($item);
                Debt::updateOrCreate(['household_id' => $household->id, 'month' => $item['month']], ['amount' => 0, 'status' => 'DA_THU', 'payment_id' => $payment->id]);
            }
            Invoice::create(['payment_id' => $payment->id, 'status' => 'CHO_PHAT_HANH']);
            AuditLog::create(['user_id' => $collectorId, 'action' => 'COLLECT_PAYMENT', 'entity_type' => Payment::class, 'entity_id' => $payment->id, 'new_values' => $payment->toArray(), 'ip_address' => $ip]);

            return $payment->load(['household', 'months', 'invoice']);
        });
    }

    public function nextSuggestedMonth(Household $household): string
    {
        $latest = PaymentMonth::whereHas('payment', fn ($q) => $q->where('household_id', $household->id))->max('month');

        return $latest ? CarbonImmutable::parse($latest)->addMonth()->format('Y-m') : now()->format('Y-m');
    }
}
