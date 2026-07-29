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
        // These fields represent accounting months, not instants in time.
        // Serialize them as date-only values so conversion to UTC cannot move
        // them into the previous month on mobile clients.
        return ['from_month' => 'date:Y-m-d', 'to_month' => 'date:Y-m-d', 'amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
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
