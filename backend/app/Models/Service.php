<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'monthly_price', 'tax_fee', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['monthly_price' => 'decimal:2', 'tax_fee' => 'decimal:2', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::created(function (Service $service) {
            if (\Illuminate\Support\Facades\Schema::hasTable('service_price_periods')) {
                $service->pricePeriods()->create([
                    'document_number' => 'GIÁ KHỞI TẠO',
                    'document_name' => 'Mức giá khi tạo dịch vụ',
                    'effective_from' => '2000-01-01',
                    'monthly_price' => $service->monthly_price,
                    'tax_fee' => $service->tax_fee,
                    'is_active' => true,
                ]);
            }
        });
    }

    public function pricePeriods()
    {
        return $this->hasMany(ServicePricePeriod::class)->orderByDesc('effective_from');
    }
}
