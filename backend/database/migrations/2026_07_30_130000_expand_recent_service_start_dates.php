<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $subscriptions = DB::table('household_services')->get(['id', 'service_id', 'started_at']);
        foreach ($subscriptions as $subscription) {
            $firstPriceDate = DB::table('service_price_periods')->where('service_id', $subscription->service_id)
                ->where('is_active', true)->whereDate('effective_from', '>=', '2020-01-01')->min('effective_from');
            if ($firstPriceDate && $firstPriceDate < $subscription->started_at) {
                DB::table('household_services')->where('id', $subscription->id)->update(['started_at' => $firstPriceDate, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void {}
};
