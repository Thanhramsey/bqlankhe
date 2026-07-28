<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'household_id', 'collector_id', 'from_month', 'to_month', 'amount', 'status', 'payment_method', 'paid_at', 'note'];

    protected function casts(): array
    {
        return ['from_month' => 'date', 'to_month' => 'date', 'amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function months()
    {
        return $this->hasMany(PaymentMonth::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
