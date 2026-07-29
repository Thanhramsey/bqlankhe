<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Material extends Model { use SoftDeletes; protected $fillable=['material_category_id','created_by','code','name','unit','supplier','price','image_path','description','is_active']; protected $appends=['image_url','total_stock']; protected function casts():array{return ['is_active'=>'boolean','price'=>'decimal:2'];} public function category(){return $this->belongsTo(MaterialCategory::class,'material_category_id');} public function creator(){return $this->belongsTo(User::class,'created_by');} public function stocks(){return $this->hasMany(WarehouseStock::class);} public function getImageUrlAttribute(){return $this->image_path?asset('storage/'.$this->image_path):null;} public function getTotalStockAttribute(){return (float)($this->relationLoaded('stocks')?$this->stocks->sum('quantity'):0);} }
