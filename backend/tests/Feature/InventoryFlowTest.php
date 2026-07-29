<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_in_out_and_overdraft_protection(): void
    {
        $permission = Permission::where('code', 'inventory.manage')->firstOrFail();
        $role = Role::create(['name' => 'Thủ kho', 'code' => 'STOREKEEPER']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);
        $token = $user->createToken('inventory-test')->plainTextToken;

        $categoryId = $this->withToken($token)->postJson('/api/v1/inventory/categories', [
            'code' => 'DUNG-CU', 'name' => 'Dụng cụ', 'description' => 'Dụng cụ vệ sinh', 'is_active' => true,
        ])->assertCreated()->json('data.id');
        $warehouseId = $this->withToken($token)->postJson('/api/v1/inventory/warehouses', [
            'code' => 'KHO-01', 'name' => 'Kho trung tâm', 'is_active' => true,
        ])->assertCreated()->json('data.id');
        $materialId = $this->withToken($token)->post('/api/v1/inventory/materials', [
            'material_category_id' => $categoryId, 'code' => 'CHOI-01', 'name' => 'Chổi quét',
            'unit' => 'Cái', 'supplier' => 'Nhà cung cấp A', 'price' => 50000, 'is_active' => 1,
        ])->assertCreated()->json('data.id');

        $base = ['warehouse_id' => $warehouseId, 'transaction_date' => '2026-07-29'];
        $this->withToken($token)->postJson('/api/v1/inventory/transactions', [...$base, 'type' => 'IN',
            'items' => [['material_id' => $materialId, 'quantity' => 10, 'unit_price' => 50000]],
        ])->assertCreated()->assertJsonPath('data.total_amount', '500000.00');
        $this->withToken($token)->postJson('/api/v1/inventory/transactions', [...$base, 'type' => 'OUT',
            'items' => [['material_id' => $materialId, 'quantity' => 3, 'unit_price' => 50000]],
        ])->assertCreated();

        $this->assertDatabaseHas('warehouse_stocks', ['warehouse_id' => $warehouseId, 'material_id' => $materialId, 'quantity' => 7]);
        $this->withToken($token)->postJson('/api/v1/inventory/transactions', [...$base, 'type' => 'OUT',
            'items' => [['material_id' => $materialId, 'quantity' => 8, 'unit_price' => 50000]],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('stock_transactions', 2);
        $this->withToken($token)->getJson('/api/v1/inventory/report')->assertOk()
            ->assertJsonPath('data.summary.stock_quantity', 7)
            ->assertJsonPath('data.summary.stock_value', 350000);
    }
}
