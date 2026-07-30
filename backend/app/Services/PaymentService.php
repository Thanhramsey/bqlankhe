<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Debt;
use App\Models\Household;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMonth;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private readonly ServicePricingService $pricing) {}

    public function preview(array $data): array
    {
        $from = CarbonImmutable::createFromFormat('Y-m', $data['from_month'])->startOfMonth();
        $to = CarbonImmutable::createFromFormat('Y-m', $data['to_month'])->startOfMonth();
        $household = Household::with(['services' => fn ($query) => $query->where('is_active', true)->with('service')])
            ->findOrFail($data['household_id']);
        $items = collect();

        for ($month = $from; $month->lte($to); $month = $month->addMonth()) {
            foreach ($household->services as $subscription) {
                if ($subscription->started_at->startOfMonth()->gt($month) || ($subscription->ended_at && $subscription->ended_at->startOfMonth()->lt($month))) {
                    continue;
                }
                if (PaymentMonth::where('household_service_id', $subscription->id)->whereDate('month', $month)
                    ->when($data['exclude_payment_id'] ?? null, fn ($query, $id) => $query->where('payment_id', '!=', $id))->exists()) {
                    throw ValidationException::withMessages(['from_month' => 'Tháng '.$month->format('m/Y').' đã được thu.']);
                }
                $values = $this->pricing->values($subscription->service, $month);
                $items->push(['month' => $month->format('Y-m'), 'service' => $subscription->service->name,
                    'price_period_id' => $values['period']?->id, 'document_number' => $values['period']?->document_number ?? collect($values['breakdown'])->pluck('document_number')->unique()->join(', '),
                    'document_name' => $values['period']?->document_name, 'pricing_breakdown' => $values['breakdown'], 'price' => $values['price'],
                    'tax_fee_rate' => $values['tax_rate'], 'tax_fee_amount' => $values['tax'], 'amount' => $values['total']]);
            }
        }

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['from_month' => 'Không có dịch vụ phát sinh trong khoảng tháng đã chọn.']);
        }

        return [
            'household' => $household->only(['id', 'code', 'owner_name', 'address']),
            'from_month' => $from->format('Y-m'), 'to_month' => $to->format('Y-m'),
            'month_count' => $from->diffInMonths($to) + 1,
            'subtotal' => round($items->sum('price'), 2),
            'tax_fee' => round($items->sum('tax_fee_amount'), 2),
            'total' => round($items->sum('amount'), 2), 'items' => $items,
        ];
    }

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
                    $values = $this->pricing->values($subscription->service, $month);
                    $items[] = ['household_service_id' => $subscription->id,
                        'service_price_period_id' => $values['period']?->id, 'month' => $month->toDateString(),
                        'base_price' => $values['price'], 'tax_fee_rate' => $values['tax_rate'],
                        'tax_fee_amount' => $values['tax'], 'document_number' => $values['period']?->document_number ?? collect($values['breakdown'])->pluck('document_number')->unique()->join(', '),
                        'pricing_breakdown' => $values['breakdown'],
                        'amount' => $values['total']];
                    $total += $values['total'];
                }
            }
            if (! $items) {
                throw ValidationException::withMessages(['from_month' => 'Không có dịch vụ phát sinh trong khoảng thời gian đã chọn.']);
            }
            $payment = Payment::create(['code' => 'PT'.now()->format('YmdHis').random_int(100, 999), 'household_id' => $household->id, 'collector_id' => $collectorId, 'from_month' => $from, 'to_month' => $to, 'amount' => $total, 'status' => 'DA_THU', 'payment_method' => $data['payment_method'] ?? 'TIEN_MAT', 'paid_at' => now(), 'note' => $data['note'] ?? null]);
            foreach ($items as $item) {
                $payment->months()->create($item);
                $this->syncDebt($household->id, $item['month'], 0, 'DA_THU', $payment->id);
            }
            Invoice::create(['payment_id' => $payment->id, 'status' => 'CHO_PHAT_HANH']);
            AuditLog::create(['user_id' => $collectorId, 'action' => 'COLLECT_PAYMENT', 'entity_type' => Payment::class, 'entity_id' => $payment->id, 'new_values' => $payment->toArray(), 'ip_address' => $ip]);

            return $payment->load(['household', 'months.pricePeriod', 'invoice']);
        });
    }

    public function nextSuggestedMonth(Household $household): string
    {
        $latest = PaymentMonth::whereHas('payment', fn ($q) => $q->where('household_id', $household->id))->max('month');

        return $latest ? CarbonImmutable::parse($latest)->addMonth()->format('Y-m') : now()->format('Y-m');
    }

    public function replacePending(Payment $payment, array $data, int $userId, ?string $ip = null): Payment
    {
        return DB::transaction(function () use ($payment, $data, $userId, $ip) {
            $oldId = $payment->id;
            $oldCode = $payment->code;
            $householdId = $payment->household_id;
            $collectorId = $payment->collector_id;
            $this->deletePending($payment, $userId, $ip);
            $replacement = $this->collect([...$data, 'household_id' => $householdId], $collectorId, $ip);
            Payment::withTrashed()->whereKey($oldId)->update(['code' => $oldCode.'-EDIT-'.$oldId]);
            $replacement->update(['code' => $oldCode]);
            AuditLog::create(['user_id' => $userId, 'action' => 'UPDATE_PAYMENT', 'entity_type' => Payment::class,
                'entity_id' => $replacement->id, 'old_values' => ['payment_id' => $oldId, 'code' => $oldCode],
                'new_values' => $replacement->fresh()->toArray(), 'ip_address' => $ip]);

            return $replacement->fresh()->load(['household.route', 'invoice', 'months.pricePeriod']);
        });
    }

    public function deletePending(Payment $payment, int $userId, ?string $ip = null): void
    {
        DB::transaction(function () use ($payment, $userId, $ip) {
            $payment = Payment::query()->lockForUpdate()->with(['invoice', 'months'])->findOrFail($payment->id);
            if (! $payment->invoice || $payment->invoice->status !== 'CHO_PHAT_HANH' || $payment->invoice->invoice_no) {
                throw ValidationException::withMessages(['payment' => 'Chỉ được xóa phiếu thu có hóa đơn đang chờ phát hành.']);
            }
            $old = $payment->toArray();
            foreach ($payment->months->groupBy(fn ($item) => $item->month->format('Y-m-d')) as $month => $items) {
                $this->syncDebt(
                    $payment->household_id,
                    $month,
                    $items->sum(fn ($item) => (float) $item->amount),
                    'CHUA_THU',
                    null,
                );
            }
            $payment->invoice->delete();
            $payment->months()->forceDelete();
            $payment->delete();
            AuditLog::create(['user_id' => $userId, 'action' => 'DELETE_PAYMENT', 'entity_type' => Payment::class, 'entity_id' => $payment->id, 'old_values' => $old, 'ip_address' => $ip]);
        });
    }

    private function syncDebt(int $householdId, string $month, float $amount, string $status, ?int $paymentId): void
    {
        $debt = Debt::withTrashed()
            ->where('household_id', $householdId)
            ->whereDate('month', $month)
            ->first();

        if (! $debt) {
            $debt = new Debt(['household_id' => $householdId, 'month' => $month]);
        } elseif ($debt->trashed()) {
            $debt->restore();
        }

        $debt->fill([
            'amount' => $amount,
            'status' => $status,
            'payment_id' => $paymentId,
        ])->save();
    }
}
