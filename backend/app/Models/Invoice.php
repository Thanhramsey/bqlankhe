<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = ['payment_id', 'provider', 'invoice_no', 'status', 'provider_response', 'issued_at', 'issued_by'];

    protected function casts(): array
    {
        return ['provider_response' => 'array', 'issued_at' => 'datetime'];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
