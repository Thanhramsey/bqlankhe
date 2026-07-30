<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\HouseholdService;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicePricePeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_uses_and_snapshots_price_document_for_each_month(): void
    {
        $permissions = collect([
            ['Quản lý dịch vụ', 'services.manage', 'services'],
            ['Thu phí', 'payments.create', 'payments'],
            ['Xem thu phí', 'payments.view', 'payments'],
        ])->map(fn ($item) => Permission::create(['name' => $item[0], 'code' => $item[1], 'module' => $item[2]]));
        $role = Role::create(['name' => 'Quản trị giá', 'code' => 'PRICE_TEST']);
        $role->permissions()->attach($permissions->pluck('id'));
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);
        $token = $user->createToken('test')->plainTextToken;

        $service = Service::create(['code' => 'RAC-SH', 'name' => 'Rác sinh hoạt', 'monthly_price' => 8000, 'tax_fee' => 0, 'is_active' => true]);
        $service->pricePeriods()->first()->update(['effective_to' => '2025-12-01']);
        $this->withToken($token)->postJson("/api/v1/services/{$service->id}/price-periods", [
            'document_number' => 'ABC', 'document_name' => 'Văn bản giá 6 tháng đầu năm',
            'effective_from' => '2026-01-01', 'effective_to' => '2026-06-05', 'monthly_price' => 10000, 'tax_fee' => 5, 'is_active' => true,
        ])->assertCreated();
        $this->withToken($token)->postJson("/api/v1/services/{$service->id}/price-periods", [
            'document_number' => 'BCD', 'document_name' => 'Văn bản giá 6 tháng cuối năm',
            'effective_from' => '2026-06-06', 'effective_to' => '2026-12-31', 'monthly_price' => 12000, 'tax_fee' => 10, 'is_active' => true,
        ])->assertCreated();

        $household = Household::create(['code' => 'H-GIA', 'owner_name' => 'Hộ kiểm tra giá', 'address' => 'An Khê', 'ward' => 'An Khê', 'is_active' => true]);
        HouseholdService::create(['household_id' => $household->id, 'service_id' => $service->id, 'monthly_price' => 8000, 'started_at' => '2026-01-01', 'is_active' => true]);
        $payload = ['household_id' => $household->id, 'from_month' => '2026-05', 'to_month' => '2026-07'];

        $this->withToken($token)->postJson('/api/v1/mobile/payments/preview', $payload)
            ->assertOk()->assertJsonPath('data.subtotal', 33666.67)->assertJsonPath('data.tax_fee', 2783.33)
            ->assertJsonPath('data.total', 36450)->assertJsonPath('data.items.0.document_number', 'ABC')
            ->assertJsonPath('data.items.1.document_number', 'ABC, BCD')
            ->assertJsonPath('data.items.1.pricing_breakdown.0.days', 5)
            ->assertJsonPath('data.items.1.pricing_breakdown.1.days', 25)
            ->assertJsonPath('data.items.2.document_number', 'BCD');
        $payment = $this->withToken($token)->postJson('/api/v1/mobile/payments', [...$payload, 'payment_method' => 'TIEN_MAT'])
            ->assertCreated()->assertJsonPath('data.amount', '36450.00')->json('data');

        $this->assertDatabaseHas('payment_months', ['payment_id' => $payment['id'], 'month' => '2026-05-01', 'base_price' => 10000, 'tax_fee_rate' => 5, 'tax_fee_amount' => 500, 'document_number' => 'ABC']);
        $this->assertDatabaseHas('payment_months', ['payment_id' => $payment['id'], 'month' => '2026-07-01', 'base_price' => 12000, 'tax_fee_rate' => 10, 'tax_fee_amount' => 1200, 'document_number' => 'BCD']);
    }
}
