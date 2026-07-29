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

class PaymentDeletionTest extends TestCase
{
    use RefreshDatabase;
    public function test_pending_payment_can_be_deleted_and_months_can_be_collected_again():void
    {
        $permission=Permission::firstOrCreate(['code'=>'payments.create'],['name'=>'Thu phí','module'=>'payments']);$role=Role::create(['name'=>'Thu ngân','code'=>'CASHIER']);$role->permissions()->attach($permission);$user=User::factory()->create(['is_active'=>true]);$user->roles()->attach($role);$token=$user->createToken('test')->plainTextToken;
        $service=Service::create(['code'=>'S-DEL','name'=>'Dịch vụ','monthly_price'=>100000,'tax_fee'=>0,'is_active'=>true]);$household=Household::create(['code'=>'H-DEL','owner_name'=>'Hộ xóa','address'=>'An Khê','ward'=>'An Khê','is_active'=>true]);HouseholdService::create(['household_id'=>$household->id,'service_id'=>$service->id,'monthly_price'=>100000,'started_at'=>'2026-01-01','is_active'=>true]);
        $payload=['household_ids'=>[$household->id],'from_month'=>'2026-06','to_month'=>'2026-07','payment_method'=>'TIEN_MAT'];
        $paymentId=$this->withToken($token)->postJson('/api/v1/payments',$payload)->assertCreated()->json('data.0.id');
        $this->withToken($token)->deleteJson('/api/v1/payments/'.$paymentId)->assertOk();
        $this->assertSoftDeleted('payments',['id'=>$paymentId]);$this->assertDatabaseMissing('payment_months',['payment_id'=>$paymentId]);
        $this->withToken($token)->getJson('/api/v1/households/'.$household->id.'/payment-suggestion')->assertOk()->assertJsonPath('data.next_month',now()->format('Y-m'));
        $this->withToken($token)->postJson('/api/v1/payments',$payload)->assertCreated()->assertJsonCount(2,'data.0.months');
    }
}
