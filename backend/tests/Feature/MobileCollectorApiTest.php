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

class MobileCollectorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_collector_can_preview_collect_and_read_mobile_transaction(): void
    {
        $create = Permission::create(['name' => 'Thu phí', 'code' => 'payments.create', 'module' => 'payments']);
        $view = Permission::create(['name' => 'Xem thu phí', 'code' => 'payments.view', 'module' => 'payments']);
        $role = Role::create(['name' => 'Thu ngân', 'code' => 'COLLECTOR_MOBILE']);
        $role->permissions()->attach([$create->id, $view->id]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);
        $token = $user->createToken('mobile-test')->plainTextToken;
        $service = Service::create(['code' => 'MOBILE', 'name' => 'Thu gom rác', 'monthly_price' => 150000, 'tax_fee' => 10, 'is_active' => true]);
        $household = Household::create(['code' => 'MH1', 'owner_name' => 'Hộ Mobile', 'address' => 'An Khê', 'ward' => 'An Khê', 'is_active' => true]);
        HouseholdService::create(['household_id' => $household->id, 'service_id' => $service->id, 'monthly_price' => 150000, 'started_at' => '2026-01-01', 'is_active' => true]);
        $payload = ['household_id' => $household->id, 'from_month' => '2026-07', 'to_month' => '2026-09'];

        $this->withToken($token)->getJson('/api/v1/mobile/routes')->assertOk();

        $this->withToken($token)->postJson('/api/v1/mobile/payments/preview', $payload)
            ->assertOk()->assertJsonPath('data.month_count', 3)->assertJsonPath('data.total', 495000);
        $payment = $this->withToken($token)->postJson('/api/v1/mobile/payments', [...$payload, 'payment_method' => 'CHUYEN_KHOAN'])
            ->assertCreated()
            ->assertJsonPath('data.amount', '495000.00')
            ->assertJsonPath('data.from_month', '2026-07-01')
            ->assertJsonPath('data.to_month', '2026-09-01')
            ->json('data');
        $this->withToken($token)->getJson('/api/v1/mobile/households')
            ->assertOk()->assertJsonPath('data.data.0.latest_payment.to_month', '2026-09-01');
        $this->withToken($token)->getJson('/api/v1/mobile/payments/'.$payment['id'])
            ->assertOk()->assertJsonPath('data.code', $payment['code'])->assertJsonPath('data.invoice.status', 'CHO_PHAT_HANH');
        $this->withToken($token)->postJson('/api/v1/mobile/payments/preview', $payload)->assertUnprocessable();
    }

    public function test_user_can_change_password_from_mobile(): void
    {
        $user = User::factory()->create(['username' => 'mobile.password', 'password' => 'OldPassword@1', 'is_active' => true]);
        $token = $user->createToken('mobile-test')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/auth/change-password', ['current_password' => 'OldPassword@1', 'password' => 'NewPassword@2', 'password_confirmation' => 'NewPassword@2'])->assertOk();
        $this->postJson('/api/v1/auth/login', ['identifier' => $user->username, 'password' => 'NewPassword@2'])->assertOk();
    }
}
