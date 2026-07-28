<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMonth extends Model
{
    protected $fillable = ['payment_id', 'household_service_id', 'month', 'amount'];

    protected function casts(): array
    {
        return ['month' => 'date', 'amount' => 'decimal:2'];
    }
}
