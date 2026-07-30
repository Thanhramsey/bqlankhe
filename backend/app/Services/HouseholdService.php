<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class HouseholdService
{
    public function create(array $data): Household
    {
        return DB::transaction(function () use ($data) {
            $serviceId = $data['service_id'];
            $serviceStartedAt = $data['service_started_at'] ?? null;
            unset($data['service_id'], $data['service_started_at']);
            $data['ward'] ??= 'An Khê';
            $household = Household::create($data);
            $this->syncService($household, $serviceId, $serviceStartedAt);

            return $household->load(['route', 'services.service']);
        });
    }

    public function update(Household $household, array $data): Household
    {
        return DB::transaction(function () use ($household, $data) {
            $serviceId = $data['service_id'];
            $serviceStartedAt = $data['service_started_at'] ?? null;
            unset($data['service_id'], $data['service_started_at']);
            $household->update($data);
            $this->syncService($household, $serviceId, $serviceStartedAt);

            return $household->load(['route', 'services.service']);
        });
    }

    private function syncService(Household $household, int $serviceId, ?string $startedAt = null): void
    {
        $service = Service::query()->findOrFail($serviceId);
        $household->services()->where('service_id', '!=', $serviceId)->delete();
        $subscription = $household->services()->withTrashed()->firstOrNew(['service_id' => $serviceId]);
        if (! $subscription->exists || $subscription->trashed()) {
            $subscription->monthly_price = $service->monthly_price;
            $subscription->started_at = $startedAt ?: now()->startOfMonth();
        }
        if ($startedAt) $subscription->started_at = $startedAt;
        $subscription->is_active = true;
        $subscription->deleted_at = null;
        $subscription->save();
    }
}
