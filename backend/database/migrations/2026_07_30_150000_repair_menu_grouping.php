<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $groups = [
            '#collection' => ['name' => 'Thu phí và hộ dân', 'icon' => 'cash-register', 'sort_order' => 10],
            '#finance' => ['name' => 'Hóa đơn và báo cáo', 'icon' => 'chart-box-outline', 'sort_order' => 20],
            '#inventory' => ['name' => 'Vật tư và kho', 'icon' => 'warehouse', 'sort_order' => 30],
            '#directives' => ['name' => 'Thông tin điều hành', 'icon' => 'bullhorn-outline', 'sort_order' => 32],
            '#documents' => ['name' => 'Văn bản và tài liệu', 'icon' => 'file-document-multiple-outline', 'sort_order' => 35],
            '#system' => ['name' => 'Quản trị hệ thống', 'icon' => 'cog-outline', 'sort_order' => 40],
        ];

        foreach ($groups as $path => $group) {
            DB::table('menus')->updateOrInsert(
                ['path' => $path],
                [...$group, 'permission_code' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $groupIds = DB::table('menus')->whereIn('path', array_keys($groups))->pluck('id', 'path');
        $mapping = [
            '#collection' => ['/households', '/services', '/routes', '/payments', '/debts'],
            '#finance' => ['/invoices', '/reports'],
            '#inventory' => ['/inventory'],
            '#directives' => ['/directives/sent', '/directives/inbox'],
            '#documents' => ['/documents'],
            '#system' => ['/users', '/audit-logs', '/settings'],
        ];

        foreach ($mapping as $parentPath => $childPaths) {
            DB::table('menus')->whereIn('path', $childPaths)->update([
                'parent_id' => $groupIds[$parentPath],
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Keep the grouped menu structure when rolling back unrelated deployments.
    }
};
