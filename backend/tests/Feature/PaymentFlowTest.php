<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\HouseholdService;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\InvoiceSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_range_is_calculated_and_duplicate_is_rejected(): void
    {
        $permission = Permission::create(['name' => 'Thu phí', 'code' => 'payments.create', 'module' => 'payments']);
        $viewPermission = Permission::create(['name' => 'Xem thu phí', 'code' => 'payments.view', 'module' => 'payments']);
        $role = Role::create(['name' => 'Thu ngân', 'code' => 'COLLECTOR']);
        $role->permissions()->attach([$permission->id, $viewPermission->id]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);
        $token = $user->createToken('test')->plainTextToken;
        $service = Service::create(['code' => 'S1', 'name' => 'Rác hộ dân', 'monthly_price' => 150000, 'tax_fee' => 10, 'is_active' => true]);
        $household = Household::create(['code' => 'H1', 'owner_name' => 'Hộ A', 'address' => 'An Khê', 'ward' => 'An Khê', 'is_active' => true]);
        HouseholdService::create(['household_id' => $household->id, 'service_id' => $service->id, 'monthly_price' => 30000, 'started_at' => '2026-01-01', 'is_active' => true]);
        $secondHousehold = Household::create(['code' => 'H2', 'owner_name' => 'Hộ B', 'address' => 'An Khê', 'ward' => 'An Khê', 'is_active' => true]);
        HouseholdService::create(['household_id' => $secondHousehold->id, 'service_id' => $service->id, 'monthly_price' => 30000, 'started_at' => '2026-01-01', 'is_active' => true]);
        $payload = ['household_ids' => [$household->id, $secondHousehold->id], 'from_month' => '2026-03', 'to_month' => '2026-06', 'payment_method' => 'TIEN_MAT'];
        $this->withToken($token)->getJson('/api/v1/payments/options')
            ->assertOk()->assertJsonPath('data.households.0.id', $household->id);
        $this->withToken($token)->postJson('/api/v1/payments', $payload)->assertCreated()->assertJsonCount(2, 'data')->assertJsonPath('data.0.amount', '660000.00')->assertJsonPath('data.1.amount', '660000.00')->assertJsonCount(4, 'data.0.months');
        $this->withToken($token)->getJson('/api/v1/households/'.$household->id.'/payment-suggestion')
            ->assertOk()->assertJsonPath('data.next_month', '2026-07');
        app(InvoiceSettingService::class)->update([
            'PUBLISH_SERVICE_ADDRESS_ID' => 'https://vnpt.test/Publishservice.asmx', 'WS_USER_ID' => 'ws-user', 'WS_PASSWORD_ID' => 'ws-pass',
            'C_USER_ID' => 'c-user', 'C_PASSWORD_ID' => 'c-pass', 'Mẫu số hóa đơn' => '1/001', 'Ký hiệu hóa đơn' => 'K26TTT',
            'Tên đơn vị' => 'Ban Quản lý phường An Khê', 'Mã số thuế' => '59001234', 'Địa chỉ' => 'An Khê', 'Số điện thoại' => '0969123334',
        ]);
        $paymentIds = \App\Models\Payment::orderBy('id')->pluck('id')->all();
        $paymentCodes = \App\Models\Payment::orderBy('id')->pluck('code')->all();
        Http::fakeSequence()
            ->push('<ImportAndPublishInvResponse><ImportAndPublishInvResult>OK:-'.$paymentCodes[0].'_0000001</ImportAndPublishInvResult></ImportAndPublishInvResponse>')
            ->push('<ImportAndPublishInvResponse><ImportAndPublishInvResult>OK:-'.$paymentCodes[1].'_0000002</ImportAndPublishInvResult></ImportAndPublishInvResponse>')
            ->push('<getInvViewFkeyNoPayResponse><getInvViewFkeyNoPayResult>'.base64_encode('%PDF-1.4 VNPT').'</getInvViewFkeyNoPayResult></getInvViewFkeyNoPayResponse>');
        $this->withToken($token)->postJson('/api/v1/invoices/publish', ['payment_ids' => $paymentIds])
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.fkey', $paymentCodes[0]);
        $this->assertDatabaseHas('invoices', ['payment_id' => $paymentIds[0], 'status' => 'DA_PHAT_HANH', 'invoice_no' => '0000001']);
        $this->withToken($token)->getJson('/api/v1/invoices?search=0000001')
            ->assertOk()
            ->assertJsonPath('data.items.data.0.invoice_no', '0000001')
            ->assertJsonPath('data.items.data.0.status', 'DA_PHAT_HANH');
        $this->withToken($token)->get('/api/v1/invoices-export?status=DA_PHAT_HANH')
            ->assertOk()->assertDownload();
        $this->withToken($token)->get('/api/v1/debts-export?to_month=2026-07')
            ->assertOk()->assertDownload();
        $this->withToken($token)->get('/api/v1/payments/'.$paymentIds[0].'/receipt')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->withToken($token)->get('/api/v1/payments/'.$paymentIds[0].'/invoice')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->withToken($token)->postJson('/api/v1/payments', $payload)->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertDatabaseCount('payments', 2);
    }
}
