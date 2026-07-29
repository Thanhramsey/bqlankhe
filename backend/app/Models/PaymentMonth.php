<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMonth extends Model
{
    use SoftDeletes;
    protected $fillable = ['payment_id', 'household_service_id', 'month', 'amount'];

    protected function casts(): array
    {
        return ['month' => 'date', 'amount' => 'decimal:2'];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function householdService()
    {
        return $this->belongsTo(HouseholdService::class);
    }
}
