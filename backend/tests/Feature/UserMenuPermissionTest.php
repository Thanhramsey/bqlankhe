<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMenuPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_role_and_custom_user_menus_are_applied(): void
    {
        $accountant = Role::where('code', 'ACCOUNTANT')->with('permissions')->firstOrFail();
        foreach (['payments.view', 'reports.view'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => explode('.', $code)[0]]);
            $accountant->permissions()->syncWithoutDetaching($permission);
        }
        $accountant->load('permissions');
        $this->assertTrue($accountant->permissions->contains('code', 'payments.view'));
        $this->assertTrue($accountant->permissions->contains('code', 'reports.view'));

        $invoiceMenu = Menu::where('path', '/invoices')->firstOrFail();
        $user = User::factory()->create(['is_active' => true, 'menu_access_custom' => true]);
        $user->roles()->attach($accountant);
        $user->menus()->attach($invoiceMenu);
        $token = $user->createToken('menu-test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
        $paths = collect($response->json('data.menus'))->flatMap(fn ($menu) => [
            $menu['path'], ...collect($menu['children'] ?? [])->pluck('path'),
        ]);
        $this->assertTrue($paths->contains('/invoices'));
        $this->assertFalse($paths->contains('/payments'));
        $this->assertFalse($paths->contains('/reports'));
    }

    public function test_leader_and_accountant_have_all_available_permissions(): void
    {
        $permissionCount = Permission::count();
        $this->assertGreaterThan(0, $permissionCount);
        $this->assertSame($permissionCount, Role::where('code','LEADER')->firstOrFail()->permissions()->count());
        $this->assertSame($permissionCount, Role::where('code','ACCOUNTANT')->firstOrFail()->permissions()->count());
    }
}
