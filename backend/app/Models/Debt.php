<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Debt extends Model
{
    use SoftDeletes;
    protected $fillable = ['household_id', 'month', 'amount', 'status', 'payment_id'];

    protected function casts(): array
    {
        return ['month' => 'date', 'amount' => 'decimal:2'];
    }
}
