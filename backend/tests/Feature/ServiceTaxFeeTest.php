<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTaxFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_tax_fee_percentage_is_stored_and_must_be_between_zero_and_one_hundred(): void
    {
        $permission = Permission::create([
            'name' => 'Quản lý dịch vụ',
            'code' => 'services.manage',
            'module' => 'services',
        ]);
        $role = Role::create(['name' => 'Quản trị dịch vụ', 'code' => 'SERVICE_ADMIN']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);
        $token = $user->createToken('test')->plainTextToken;

        $payload = [
            'code' => 'RAC-TEST',
            'name' => 'Thu gom rác thử nghiệm',
            'monthly_price' => 30000,
            'tax_fee' => 10,
            'is_active' => true,
        ];

        $this->withToken($token)
            ->postJson('/api/v1/services', $payload)
            ->assertCreated()
            ->assertJsonPath('data.tax_fee', '10.00');

        $this->assertDatabaseHas('services', ['code' => 'RAC-TEST', 'tax_fee' => 10]);

        $this->withToken($token)
            ->postJson('/api/v1/services', [...$payload, 'code' => 'RAC-AM', 'tax_fee' => -1])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['tax_fee']]);

        $this->withToken($token)
            ->postJson('/api/v1/services', [...$payload, 'code' => 'RAC-LON', 'tax_fee' => 101])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['tax_fee']]);
    }
}
