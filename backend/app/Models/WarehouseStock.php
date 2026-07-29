<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WarehouseStock extends Model { protected $fillable=['warehouse_id','material_id','quantity','average_price']; protected function casts():array{return ['quantity'=>'decimal:3','average_price'=>'decimal:2'];} public function warehouse(){return $this->belongsTo(Warehouse::class);} public function material(){return $this->belongsTo(Material::class);} }
