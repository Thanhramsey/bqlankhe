<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')->updateOrInsert(['path' => '/invoices'], [
            'name' => 'Hóa đơn điện tử', 'permission_code' => 'payments.view', 'sort_order' => 43,
            'icon' => 'receipt', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('path', '/invoices')->delete();
    }
};
