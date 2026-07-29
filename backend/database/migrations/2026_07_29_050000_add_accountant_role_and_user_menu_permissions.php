<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('menu_access_custom')->default(false)->after('is_active'));
        Schema::create('menu_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'menu_id']);
        });

        $now = now();
        $groups = [
            ['name' => 'Thu phí và hộ dân', 'path' => '#collection', 'icon' => 'cash-register', 'sort_order' => 10],
            ['name' => 'Hóa đơn và báo cáo', 'path' => '#finance', 'icon' => 'chart-box-outline', 'sort_order' => 20],
            ['name' => 'Vật tư và kho', 'path' => '#inventory', 'icon' => 'warehouse', 'sort_order' => 30],
            ['name' => 'Quản trị hệ thống', 'path' => '#system', 'icon' => 'cog-outline', 'sort_order' => 40],
        ];
        foreach ($groups as $group) {
            DB::table('menus')->updateOrInsert(['path' => $group['path']], [...$group, 'permission_code' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $ids = DB::table('menus')->whereIn('path', array_column($groups, 'path'))->pluck('id', 'path');
        $mapping = [
            '#collection' => ['/households', '/services', '/routes', '/payments', '/debts'],
            '#finance' => ['/invoices', '/reports'], '#inventory' => ['/inventory'],
            '#system' => ['/users', '/audit-logs', '/settings'],
        ];
        foreach ($mapping as $parent => $paths) DB::table('menus')->whereIn('path', $paths)->update(['parent_id' => $ids[$parent], 'updated_at' => $now]);

        $accountantId = DB::table('roles')->where('code', 'ACCOUNTANT')->value('id');
        if (! $accountantId) $accountantId = DB::table('roles')->insertGetId(['name' => 'Kế toán', 'code' => 'ACCOUNTANT', 'description' => 'Theo dõi thu phí, công nợ, hóa đơn, báo cáo và vật tư', 'created_at' => $now, 'updated_at' => $now]);
        $permissionIds = DB::table('permissions')->whereIn('code', ['dashboard.view','households.manage','payments.view','payments.create','reports.view','inventory.manage'])->pluck('id');
        foreach ($permissionIds as $permissionId) DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $accountantId]);
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_user');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('menu_access_custom'));
        $accountant = DB::table('roles')->where('code', 'ACCOUNTANT')->first();
        if ($accountant) { DB::table('permission_role')->where('role_id', $accountant->id)->delete(); DB::table('roles')->where('id', $accountant->id)->delete(); }
        $groupIds = DB::table('menus')->whereIn('path', ['#collection','#finance','#inventory','#system'])->pluck('id');
        DB::table('menus')->whereIn('parent_id', $groupIds)->update(['parent_id' => null]);
        DB::table('menus')->whereIn('id', $groupIds)->delete();
    }
};
