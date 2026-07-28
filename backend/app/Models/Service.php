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
}
