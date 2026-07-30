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
        $permissionNames = ['directives.receive' => 'Nhận thông tin điều hành', 'directives.send' => 'Gửi thông tin điều hành', 'documents.view' => 'Xem văn bản, tài liệu', 'documents.manage' => 'Quản lý văn bản, tài liệu', 'dashboard.view' => 'Xem dashboard', 'users.manage' => 'Quản lý người dùng', 'routes.manage' => 'Quản lý tuyến thu', 'employees.manage' => 'Quản lý nhân viên', 'households.manage' => 'Quản lý hộ dân', 'services.manage' => 'Quản lý dịch vụ', 'payments.view' => 'Xem thu phí', 'payments.create' => 'Thực hiện thu phí', 'settings.manage' => 'Cấu hình hệ thống', 'reports.view' => 'Xem báo cáo'];
        foreach ($permissionNames as $code => $name) {
            Permission::updateOrCreate(['code' => $code], ['name' => $name, 'module' => explode('.', $code)[0]]);
        }
        $admin = Role::updateOrCreate(['code' => 'ADMIN'], ['name' => 'Quản trị viên']);
        $admin->permissions()->sync(Permission::pluck('id'));
        $collector = Role::updateOrCreate(['code' => 'COLLECTOR'], ['name' => 'Nhân viên thu phí']);
        $collector->permissions()->sync(Permission::whereIn('code', ['dashboard.view', 'households.manage', 'payments.view', 'payments.create', 'documents.view', 'directives.receive'])->pluck('id'));
        $accountant = Role::updateOrCreate(['code' => 'ACCOUNTANT'], ['name' => 'Kế toán', 'description' => 'Toàn quyền nghiệp vụ, menu theo phân công']);
        $accountant->permissions()->sync(Permission::pluck('id'));
        $leader = Role::updateOrCreate(['code' => 'LEADER'], ['name' => 'Lãnh đạo', 'description' => 'Lãnh đạo đơn vị - toàn quyền nghiệp vụ, menu theo phân công']);
        $leader->permissions()->sync(Permission::pluck('id'));
        $user = User::updateOrCreate(['email' => 'admin@ankhe.local'], ['name' => 'Quản trị hệ thống', 'phone' => '0900000000', 'password' => Hash::make('Admin@123'), 'is_active' => true]);
        $user->update(['username' => 'admin']);
        $user->roles()->sync([$admin->id]);
        $menus = [['Tổng quan', '/', 'dashboard.view', 0, 'dashboard'], ['Hộ dân', '/households', 'households.manage', 10, 'home'], ['Dịch vụ', '/services', 'services.manage', 20, 'layers'], ['Tuyến thu', '/routes', 'routes.manage', 30, 'route'], ['Thu phí', '/payments', 'payments.view', 40, 'wallet'], ['Hóa đơn điện tử', '/invoices', 'payments.view', 43, 'receipt'], ['Công nợ', '/debts', 'payments.view', 45, 'alert'], ['Báo cáo', '/reports', 'reports.view', 48, 'chart'], ['Người dùng', '/users', 'users.manage', 50, 'users'], ['Log hệ thống', '/audit-logs', 'settings.manage', 55, 'history'], ['Cấu hình', '/settings', 'settings.manage', 60, 'settings']];
        foreach ($menus as [$name,$path,$permission,$sort,$icon]) {
            Menu::updateOrCreate(['path' => $path], ['name' => $name, 'permission_code' => $permission, 'sort_order' => $sort, 'icon' => $icon, 'is_active' => true]);
        }
        $menuGroups = [
            '#collection' => ['name' => 'Thu phí và hộ dân', 'icon' => 'cash-register', 'sort_order' => 10],
            '#finance' => ['name' => 'Hóa đơn và báo cáo', 'icon' => 'chart-box-outline', 'sort_order' => 20],
            '#inventory' => ['name' => 'Vật tư và kho', 'icon' => 'warehouse', 'sort_order' => 30],
            '#directives' => ['name' => 'Thông tin điều hành', 'icon' => 'bullhorn-outline', 'sort_order' => 32],
            '#documents' => ['name' => 'Văn bản và tài liệu', 'icon' => 'file-document-multiple-outline', 'sort_order' => 35],
            '#system' => ['name' => 'Quản trị hệ thống', 'icon' => 'cog-outline', 'sort_order' => 40],
        ];
        foreach ($menuGroups as $path => $group) {
            Menu::updateOrCreate(['path' => $path], [...$group, 'permission_code' => null, 'is_active' => true]);
        }
        $menuGroupIds = Menu::whereIn('path', array_keys($menuGroups))->pluck('id', 'path');
        $menuMapping = [
            '#collection' => ['/households', '/services', '/routes', '/payments', '/debts'],
            '#finance' => ['/invoices', '/reports'],
            '#inventory' => ['/inventory'],
            '#directives' => ['/directives/sent', '/directives/inbox'],
            '#documents' => ['/documents'],
            '#system' => ['/users', '/audit-logs', '/settings'],
        ];
        foreach ($menuMapping as $parentPath => $childPaths) {
            Menu::whereIn('path', $childPaths)->update(['parent_id' => $menuGroupIds[$parentPath]]);
        }
        $route = CollectionRoute::updateOrCreate(['code' => 'AK-01'], ['name' => 'Tuyến trung tâm', 'description' => 'Tuyến thu phí mẫu', 'is_active' => true]);
        $service = Service::updateOrCreate(['code' => 'RAC-HO'], ['name' => 'Thu gom rác hộ gia đình', 'monthly_price' => 30000, 'tax_fee' => 0, 'description' => 'Phí thu gom hàng tháng', 'is_active' => true]);
        $household = Household::updateOrCreate(['code' => 'HD-0001'], ['owner_name' => 'Nguyễn Văn Mẫu', 'phone' => '0901234567', 'address' => '01 Quang Trung', 'ward' => 'An Khê', 'collection_route_id' => $route->id, 'is_active' => true]);
        HouseholdService::updateOrCreate(['household_id' => $household->id, 'service_id' => $service->id], ['monthly_price' => $service->monthly_price, 'started_at' => now()->startOfYear(), 'is_active' => true]);
        SystemSetting::updateOrCreate(['key' => 'organization_name'], ['value' => 'Ban Quản lý phường An Khê', 'type' => 'string', 'group' => 'general', 'description' => 'Tên đơn vị']);
        $invoiceSettings = [
            'Tên đơn vị' => ['Ban Quản lý phường An Khê', 'organization'], 'Mã số thuế' => ['59001234', 'organization'],
            'Số điện thoại' => ['0969123334', 'organization'], 'Địa chỉ' => ['34 Hoàng Hoa Thám, Phường An Khê, Tỉnh Gia Lai, Việt Nam', 'organization'],
            'Số tài khoản ngân hàng' => ['0969124469', 'bank'], 'Người đại diện' => ['', 'organization'],
            'Mẫu số hóa đơn' => ['1/001', 'invoice'], 'Ký hiệu hóa đơn' => ['K26TTT', 'invoice'],
            'PUBLISH_SERVICE_ADDRESS_ID' => ['https://bvdkphutho-tt78admindemo.vnpt-invoice.com.vn/Publishservice.asmx', 'vnpt'],
            'BUSINESS_SERVICE_ADDRESS_ID' => ['https://bvdkphutho-tt78admindemo.vnpt-invoice.com.vn/businessService.asmx', 'vnpt'],
            'PORTAL_SERVICE_ADDRESS_ID' => ['https://bvdkphutho-tt78admindemo.vnpt-invoice.com.vn/portalservice.asmx', 'vnpt'],
            'C_PASSWORD_ID' => ['', 'vnpt'], 'C_USER_ID' => ['bvdkphuthoadmin_demo', 'vnpt'], 'WS_PASSWORD_ID' => ['', 'vnpt'], 'WS_USER_ID' => ['bvdkphuthows', 'vnpt'],
            'Mã Ngân Hàng' => ['BIDV', 'bank'], 'Tên chủ tài khoản' => ['Trịnh Tấn Thành', 'bank'],
        ];
        foreach ($invoiceSettings as $key => [$value, $group]) SystemSetting::updateOrCreate(['key' => $key], ['value' => $value, 'type' => 'string', 'is_secret' => in_array($key, ['C_PASSWORD_ID', 'WS_PASSWORD_ID']), 'group' => $group, 'description' => $key]);
    }
}
