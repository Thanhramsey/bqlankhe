<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class StockTransaction extends Model { use SoftDeletes; protected $fillable=['warehouse_id','created_by','code','type','transaction_date','partner','reference_no','status','total_amount','note']; protected function casts():array{return ['transaction_date'=>'date:Y-m-d','total_amount'=>'decimal:2'];} public function warehouse(){return $this->belongsTo(Warehouse::class);} public function creator(){return $this->belongsTo(User::class,'created_by');} public function items(){return $this->hasMany(StockTransactionItem::class);} public function documents(){return $this->hasMany(StockDocument::class);} }
