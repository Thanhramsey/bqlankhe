<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SystemSetting::updateOrCreate(['key' => 'Link tra cứu hóa đơn'], [
            'value' => '', 'type' => 'string', 'is_secret' => false, 'group' => 'invoice',
            'description' => 'Dùng {fkey} trong đường dẫn; nếu không có, hệ thống tự thêm tham số fkey.',
        ]);
    }

    public function down(): void
    {
        SystemSetting::where('key', 'Link tra cứu hóa đơn')->delete();
    }
};
