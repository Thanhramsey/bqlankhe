<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $subscriptions = DB::table('household_services')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('payment_months')->whereColumn('payment_months.household_service_id', 'household_services.id'))
            ->get(['id', 'service_id', 'started_at']);

        foreach ($subscriptions as $subscription) {
            $firstPriceDate = DB::table('service_price_periods')->where('service_id', $subscription->service_id)
                ->where('is_active', true)->min('effective_from');
            if ($firstPriceDate && $firstPriceDate < $subscription->started_at) {
                DB::table('household_services')->where('id', $subscription->id)->update(['started_at' => $firstPriceDate, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void {}
};
