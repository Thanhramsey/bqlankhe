<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'collection_route_id', 'code', 'name', 'phone', 'address', 'is_active'];

    public function route()
    {
        return $this->belongsTo(CollectionRoute::class, 'collection_route_id');
    }
}
