<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_price_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('document_number');
            $table->string('document_name')->nullable();
            $table->date('document_date')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('monthly_price', 15, 2);
            $table->decimal('tax_fee', 8, 2)->default(0);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['service_id', 'effective_from', 'effective_to'], 'service_price_period_range');
        });

        Schema::table('payment_months', function (Blueprint $table) {
            $table->foreignId('service_price_period_id')->nullable()->after('household_service_id')->constrained()->nullOnDelete();
            $table->decimal('base_price', 15, 2)->nullable()->after('month');
            $table->decimal('tax_fee_rate', 8, 2)->nullable()->after('base_price');
            $table->decimal('tax_fee_amount', 15, 2)->nullable()->after('tax_fee_rate');
            $table->string('document_number')->nullable()->after('tax_fee_amount');
        });

        $now = now();
        foreach (DB::table('services')->get() as $service) {
            DB::table('service_price_periods')->insert([
                'service_id' => $service->id,
                'document_number' => 'GIÁ KHỞI TẠO',
                'document_name' => 'Mức giá trước khi quản lý theo văn bản',
                'effective_from' => '2000-01-01',
                'effective_to' => null,
                'monthly_price' => $service->monthly_price,
                'tax_fee' => $service->tax_fee ?? 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('payment_months')->get() as $month) {
            DB::table('payment_months')->where('id', $month->id)->update([
                'base_price' => $month->amount,
                'tax_fee_rate' => 0,
                'tax_fee_amount' => 0,
                'document_number' => 'DỮ LIỆU CŨ',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('payment_months', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_price_period_id');
            $table->dropColumn(['base_price', 'tax_fee_rate', 'tax_fee_amount', 'document_number']);
        });
        Schema::dropIfExists('service_price_periods');
    }
};
