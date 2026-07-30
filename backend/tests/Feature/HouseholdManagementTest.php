<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\HouseholdExcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class HouseholdManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_household_can_be_created_searched_updated_and_soft_deleted(): void
    {
        $permission = Permission::create(['name' => 'Quản lý hộ dân', 'code' => 'households.manage', 'module' => 'households']);
        $role = Role::create(['name' => 'Quản lý hộ dân', 'code' => 'HOUSEHOLD_ADMIN']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);
        $token = $user->createToken('test')->plainTextToken;
        $service = Service::create(['code' => 'RAC-HO', 'name' => 'Rác hộ dân', 'monthly_price' => 150000, 'tax_fee' => 10, 'is_active' => true]);
        $this->withToken($token)->getJson('/api/v1/households/options')
            ->assertOk()->assertJsonPath('data.services.0.id', $service->id);
        $payload = [
            'code' => 'HD-0001', 'sequence_number' => 1, 'owner_name' => 'Nguyễn Văn A',
            'phone' => '0905000000', 'identity_number' => '048000000001', 'email' => 'vana@example.com',
            'tax_code' => '0400000001', 'representative' => 'Nguyễn Văn A', 'address' => 'An Khê',
            'invoice_address' => 'Trụ sở Công ty A, Gia Lai',
            'service_id' => $service->id, 'note' => 'Hộ mẫu', 'is_active' => true,
        ];

        $created = $this->withToken($token)->postJson('/api/v1/households', $payload)
            ->assertCreated()->assertJsonPath('data.identity_number', '048000000001')
            ->assertJsonPath('data.invoice_address', 'Trụ sở Công ty A, Gia Lai')
            ->assertJsonPath('data.service_id', $service->id)->json('data');

        $this->withToken($token)->getJson('/api/v1/households?search=0400000001')
            ->assertOk()->assertJsonCount(1, 'data.data');

        $this->withToken($token)->getJson('/api/v1/households?search=Công ty A')
            ->assertOk()->assertJsonCount(1, 'data.data');

        $this->withToken($token)->getJson('/api/v1/households?service_id='.$service->id)
            ->assertOk()->assertJsonCount(1, 'data.data');

        $this->withToken($token)->getJson('/api/v1/households/'.$created['id'].'/payments')
            ->assertOk()->assertJsonCount(0, 'data.data');

        $this->withToken($token)->putJson('/api/v1/households/'.$created['id'], [...$payload, 'owner_name' => 'Nguyễn Văn B'])
            ->assertOk()->assertJsonPath('data.owner_name', 'Nguyễn Văn B');

        $this->withToken($token)->get('/api/v1/households-export?search=Nguyễn')
            ->assertOk()->assertDownload();

        $this->withToken($token)->deleteJson('/api/v1/households/'.$created['id'])->assertOk();
        $this->assertSoftDeleted('households', ['id' => $created['id']]);
        $this->withToken($token)->postJson('/api/v1/households/'.$created['id'].'/restore')->assertOk();
        $this->assertDatabaseHas('households', ['id' => $created['id'], 'deleted_at' => null]);
    }

    public function test_household_excel_template_has_expected_structure(): void
    {
        $path = app(HouseholdExcelService::class)->template();
        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName('HoDan');

        $this->assertNotNull($sheet);
        $this->assertSame('Mã hộ *', $sheet->getCell('B1')->getValue());
        $this->assertSame('Địa chỉ HĐ', $sheet->getCell('F1')->getValue());
        $this->assertSame('Mã dịch vụ *', $sheet->getCell('I1')->getValue());
        $this->assertSame('Ngày bắt đầu dịch vụ (dd/mm/yyyy)', $sheet->getCell('J1')->getValue());
        $this->assertSame('01/01/2026', $sheet->getCell('J2')->getValue());
        $this->assertSame('list', $sheet->getCell('O2')->getDataValidation()->getType());

        @unlink($path);
    }
}
