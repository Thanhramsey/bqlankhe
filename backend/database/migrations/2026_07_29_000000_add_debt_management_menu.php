<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')->updateOrInsert(['path' => '/debts'], [
            'name' => 'Công nợ', 'permission_code' => 'payments.view', 'sort_order' => 45,
            'icon' => 'alert', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('path', '/debts')->delete();
    }
};
