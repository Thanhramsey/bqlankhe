<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')->updateOrInsert(['path' => '/reports'], [
            'name' => 'Báo cáo', 'permission_code' => 'reports.view', 'sort_order' => 48,
            'icon' => 'chart', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    public function down(): void { DB::table('menus')->where('path', '/reports')->delete(); }
};
