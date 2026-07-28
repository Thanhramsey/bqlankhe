<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollectionRoute extends Model
{
    use SoftDeletes;

    protected $fillable = ['neighborhood_id', 'code', 'name', 'description', 'is_active'];

    public function neighborhood() { return $this->belongsTo(Neighborhood::class); }
    public function users() { return $this->belongsToMany(User::class)->withTimestamps(); }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
