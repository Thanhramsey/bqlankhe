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

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_range_is_calculated_and_duplicate_is_rejected(): void
    {
        $permission = Permission::create(['name' => 'Thu phí', 'code' => 'payments.create', 'module' => 'payments']);
        $role = Role::create(['name' => 'Thu ngân', 'code' => 'COLLECTOR']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);
        $token = $user->createToken('test')->plainTextToken;
        $service = Service::create(['code' => 'S1', 'name' => 'Rác hộ dân', 'monthly_price' => 30000, 'is_active' => true]);
        $household = Household::create(['code' => 'H1', 'owner_name' => 'Hộ A', 'address' => 'An Khê', 'ward' => 'An Khê', 'is_active' => true]);
        HouseholdService::create(['household_id' => $household->id, 'service_id' => $service->id, 'monthly_price' => 30000, 'started_at' => '2026-01-01', 'is_active' => true]);
        $payload = ['household_id' => $household->id, 'from_month' => '2026-03', 'to_month' => '2026-05', 'payment_method' => 'TIEN_MAT'];
        $this->withToken($token)->postJson('/api/v1/payments', $payload)->assertCreated()->assertJsonPath('data.amount', '90000.00')->assertJsonCount(3, 'data.months');
        $this->withToken($token)->postJson('/api/v1/payments', $payload)->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertDatabaseCount('payments', 1);
    }
}
