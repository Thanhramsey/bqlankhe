<?php

namespace Database\Seeders;

use App\Models\CollectionRoute;
use App\Models\Household;
use App\Models\HouseholdService;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissionNames = ['dashboard.view' => 'Xem dashboard', 'users.manage' => 'Quản lý người dùng', 'routes.manage' => 'Quản lý tuyến thu', 'employees.manage' => 'Quản lý nhân viên', 'households.manage' => 'Quản lý hộ dân', 'services.manage' => 'Quản lý dịch vụ', 'payments.view' => 'Xem thu phí', 'payments.create' => 'Thực hiện thu phí', 'settings.manage' => 'Cấu hình hệ thống', 'reports.view' => 'Xem báo cáo'];
        foreach ($permissionNames as $code => $name) {
            Permission::updateOrCreate(['code' => $code], ['name' => $name, 'module' => explode('.', $code)[0]]);
        }
        $admin = Role::updateOrCreate(['code' => 'ADMIN'], ['name' => 'Quản trị viên']);
        $admin->permissions()->sync(Permission::pluck('id'));
        $collector = Role::updateOrCreate(['code' => 'COLLECTOR'], ['name' => 'Nhân viên thu phí']);
        $collector->permissions()->sync(Permission::whereIn('code', ['dashboard.view', 'households.manage', 'payments.view', 'payments.create'])->pluck('id'));
        $user = User::updateOrCreate(['email' => 'admin@ankhe.local'], ['name' => 'Quản trị hệ thống', 'phone' => '0900000000', 'password' => Hash::make('Admin@123'), 'is_active' => true]);
        $user->update(['username' => 'admin']);
        $user->roles()->sync([$admin->id]);
        $menus = [['Tổng quan', '/', 'dashboard.view', 0, 'dashboard'], ['Hộ dân', '/households', 'households.manage', 10, 'home'], ['Dịch vụ', '/services', 'services.manage', 20, 'layers'], ['Tuyến thu', '/routes', 'routes.manage', 30, 'route'], ['Thu phí', '/payments', 'payments.view', 40, 'wallet'], ['Người dùng', '/users', 'users.manage', 50, 'users'], ['Cấu hình', '/settings', 'settings.manage', 60, 'settings']];
        foreach ($menus as [$name,$path,$permission,$sort,$icon]) {
            Menu::updateOrCreate(['path' => $path], ['name' => $name, 'permission_code' => $permission, 'sort_order' => $sort, 'icon' => $icon, 'is_active' => true]);
        }
        $route = CollectionRoute::updateOrCreate(['code' => 'AK-01'], ['name' => 'Tuyến trung tâm', 'description' => 'Tuyến thu phí mẫu', 'is_active' => true]);
        $service = Service::updateOrCreate(['code' => 'RAC-HO'], ['name' => 'Thu gom rác hộ gia đình', 'monthly_price' => 30000, 'tax_fee' => 0, 'description' => 'Phí thu gom hàng tháng', 'is_active' => true]);
        $household = Household::updateOrCreate(['code' => 'HD-0001'], ['owner_name' => 'Nguyễn Văn Mẫu', 'phone' => '0901234567', 'address' => '01 Quang Trung', 'ward' => 'An Khê', 'collection_route_id' => $route->id, 'is_active' => true]);
        HouseholdService::updateOrCreate(['household_id' => $household->id, 'service_id' => $service->id], ['monthly_price' => $service->monthly_price, 'started_at' => now()->startOfYear(), 'is_active' => true]);
        SystemSetting::updateOrCreate(['key' => 'organization_name'], ['value' => 'Ban Quản lý phường An Khê', 'type' => 'string', 'group' => 'general', 'description' => 'Tên đơn vị']);
    }
}
