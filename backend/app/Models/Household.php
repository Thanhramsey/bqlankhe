<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Household extends Model
{
    use SoftDeletes;

    protected $fillable = ['collection_route_id', 'code', 'owner_name', 'phone', 'address', 'ward', 'note', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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
