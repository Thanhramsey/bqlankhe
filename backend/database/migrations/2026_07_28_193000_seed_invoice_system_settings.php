<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            'Tên đơn vị' => ['Ban Quản lý phường An Khê', 'organization'], 'Mã số thuế' => ['59001234', 'organization'],
            'Số điện thoại' => ['0969123334', 'organization'], 'Địa chỉ' => ['34 Hoàng Hoa Thám, Phường An Khê, Tỉnh Gia Lai, Việt Nam', 'organization'],
            'Số tài khoản ngân hàng' => ['0969124469', 'bank'], 'Người đại diện' => ['', 'organization'], 'Mẫu số hóa đơn' => ['1/001', 'invoice'],
            'Ký hiệu hóa đơn' => ['K26TTT', 'invoice'], 'PUBLISH_SERVICE_ADDRESS_ID' => ['https://bvdkphutho-tt78admindemo.vnpt-invoice.com.vn/Publishservice.asmx', 'vnpt'],
            'BUSINESS_SERVICE_ADDRESS_ID' => ['https://bvdkphutho-tt78admindemo.vnpt-invoice.com.vn/businessService.asmx', 'vnpt'],
            'PORTAL_SERVICE_ADDRESS_ID' => ['https://bvdkphutho-tt78admindemo.vnpt-invoice.com.vn/portalservice.asmx', 'vnpt'],
            'C_PASSWORD_ID' => ['', 'vnpt'], 'C_USER_ID' => ['bvdkphuthoadmin_demo', 'vnpt'], 'WS_PASSWORD_ID' => ['', 'vnpt'],
            'WS_USER_ID' => ['bvdkphuthows', 'vnpt'], 'Mã Ngân Hàng' => ['BIDV', 'bank'], 'Tên chủ tài khoản' => ['Trịnh Tấn Thành', 'bank'],
        ];
        foreach ($settings as $key => [$value, $group]) DB::table('system_settings')->insertOrIgnore(['key' => $key, 'value' => $value, 'type' => 'string', 'is_secret' => in_array($key, ['C_PASSWORD_ID', 'WS_PASSWORD_ID']), 'group' => $group, 'description' => $key, 'updated_at' => now(), 'created_at' => now()]);
    }

    public function down(): void {}
};
