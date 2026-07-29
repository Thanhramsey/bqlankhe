<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Household extends Model
{
    use SoftDeletes;

    protected $fillable = ['collection_route_id', 'code', 'sequence_number', 'owner_name', 'phone', 'identity_number', 'email', 'tax_code', 'representative', 'address', 'invoice_address', 'ward', 'note', 'is_active'];

    protected function casts(): array
    {
        return ['sequence_number' => 'integer', 'is_active' => 'boolean'];
    }

    public function route()
    {
        return $this->belongsTo(CollectionRoute::class, 'collection_route_id');
    }

    public function services()
    {
        return $this->hasMany(HouseholdService::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
