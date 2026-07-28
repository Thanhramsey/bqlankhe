<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Debt extends Model
{
    protected $fillable = ['household_id', 'month', 'amount', 'status', 'payment_id'];

    protected function casts(): array
    {
        return ['month' => 'date', 'amount' => 'decimal:2'];
    }
}
