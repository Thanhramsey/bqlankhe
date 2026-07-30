<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['paid_at', 'id'], 'payments_paid_at_id_index');
            $table->index(['collector_id', 'paid_at'], 'payments_collector_paid_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_paid_at_id_index');
            $table->dropIndex('payments_collector_paid_at_index');
        });
    }
};
