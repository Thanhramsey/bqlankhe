<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_months', fn (Blueprint $table) => $table->json('pricing_breakdown')->nullable()->after('document_number'));
    }

    public function down(): void
    {
        Schema::table('payment_months', fn (Blueprint $table) => $table->dropColumn('pricing_breakdown'));
    }
};
