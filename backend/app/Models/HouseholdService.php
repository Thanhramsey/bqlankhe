<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HouseholdService extends Model
{
    use SoftDeletes;

    protected $fillable = ['household_id', 'service_id', 'monthly_price', 'started_at', 'ended_at', 'is_active'];

    protected function casts(): array
    {
        return ['monthly_price' => 'decimal:2', 'started_at' => 'date', 'ended_at' => 'date', 'is_active' => 'boolean'];
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
