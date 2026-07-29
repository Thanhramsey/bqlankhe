<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Warehouse extends Model { use SoftDeletes; protected $fillable=['code','name','note','is_active']; protected function casts():array{return ['is_active'=>'boolean'];} public function stocks(){return $this->hasMany(WarehouseStock::class);} }
