<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class InvoiceSettingService
{
    public const DEFINITIONS = [
        'Tên đơn vị' => ['organization', false], 'Mã số thuế' => ['organization', false], 'Số điện thoại' => ['organization', false],
        'Địa chỉ' => ['organization', false], 'Số tài khoản ngân hàng' => ['bank', false], 'Người đại diện' => ['organization', false],
        'Mẫu số hóa đơn' => ['invoice', false], 'Ký hiệu hóa đơn' => ['invoice', false], 'Link tra cứu hóa đơn' => ['invoice', false],
        'PUBLISH_SERVICE_ADDRESS_ID' => ['vnpt', false], 'BUSINESS_SERVICE_ADDRESS_ID' => ['vnpt', false], 'PORTAL_SERVICE_ADDRESS_ID' => ['vnpt', false],
        'C_PASSWORD_ID' => ['vnpt', true], 'C_USER_ID' => ['vnpt', false], 'WS_PASSWORD_ID' => ['vnpt', true], 'WS_USER_ID' => ['vnpt', false],
        'Mã Ngân Hàng' => ['bank', false], 'Tên chủ tài khoản' => ['bank', false],
    ];

    public function forUi(): array
    {
        return SystemSetting::query()->whereIn('key', array_keys(self::DEFINITIONS))->orderBy('id')->get()->map(fn ($setting) => [
            'key' => $setting->key, 'value' => $setting->is_secret ? '' : $setting->value, 'has_value' => $setting->is_secret && filled($setting->value),
            'is_secret' => $setting->is_secret, 'group' => $setting->group, 'description' => $setting->description,
        ])->all();
    }

    public function update(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! isset(self::DEFINITIONS[$key])) continue;
            [$group, $secret] = self::DEFINITIONS[$key];
            $setting = SystemSetting::firstOrNew(['key' => $key]);
            if ($secret && ($value === null || $value === '')) continue;
            $setting->fill(['value' => $secret ? Crypt::encryptString((string) $value) : (string) ($value ?? ''), 'type' => 'string', 'is_secret' => $secret, 'group' => $group, 'description' => $key]);
            $setting->save();
        }
    }

    public function loadForPublish(): array
    {
        $settings = SystemSetting::query()->whereIn('key', array_keys(self::DEFINITIONS))->get()->keyBy('key');
        $values = [];
        foreach (self::DEFINITIONS as $key => [, $secret]) {
            $raw = $settings->get($key)?->value;
            if ($secret && filled($raw)) {
                try { $raw = Crypt::decryptString($raw); } catch (\Throwable) { $raw = ''; }
            }
            $values[$key] = trim((string) $raw);
        }
        foreach (['PUBLISH_SERVICE_ADDRESS_ID', 'WS_USER_ID', 'WS_PASSWORD_ID', 'C_USER_ID', 'C_PASSWORD_ID', 'Mẫu số hóa đơn', 'Ký hiệu hóa đơn'] as $required) {
            if ($values[$required] === '') throw ValidationException::withMessages(['settings' => "Thiếu tham số hệ thống: {$required}"]);
        }
        return $values;
    }
}
