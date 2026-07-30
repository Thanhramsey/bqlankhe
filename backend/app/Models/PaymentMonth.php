<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMonth extends Model
{
    use SoftDeletes;
    protected $fillable = ['payment_id', 'household_service_id', 'service_price_period_id', 'month', 'base_price', 'tax_fee_rate', 'tax_fee_amount', 'document_number', 'pricing_breakdown', 'amount'];

    protected function casts(): array
    {
        return ['month' => 'date:Y-m-d', 'base_price' => 'decimal:2', 'tax_fee_rate' => 'decimal:2', 'tax_fee_amount' => 'decimal:2', 'pricing_breakdown' => 'array', 'amount' => 'decimal:2'];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function householdService()
    {
        return $this->belongsTo(HouseholdService::class);
    }

    public function pricePeriod()
    {
        return $this->belongsTo(ServicePricePeriod::class, 'service_price_period_id');
    }
}
