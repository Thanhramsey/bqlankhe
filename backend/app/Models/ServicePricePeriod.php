<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePricePeriod extends Model
{
    use SoftDeletes;

    protected $fillable = ['service_id', 'document_id', 'document_number', 'document_name', 'document_date', 'effective_from', 'effective_to', 'monthly_price', 'tax_fee', 'note', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['document_date' => 'date:Y-m-d', 'effective_from' => 'date:Y-m-d', 'effective_to' => 'date:Y-m-d', 'monthly_price' => 'decimal:2', 'tax_fee' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function service() { return $this->belongsTo(Service::class); }
    public function document() { return $this->belongsTo(Document::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function paymentMonths() { return $this->hasMany(PaymentMonth::class); }
}
