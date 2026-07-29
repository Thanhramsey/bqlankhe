<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')->updateOrInsert(['path' => '/audit-logs'], [
            'name' => 'Log hệ thống', 'permission_code' => 'settings.manage', 'sort_order' => 55,
            'icon' => 'history', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('path', '/audit-logs')->delete();
    }
};
