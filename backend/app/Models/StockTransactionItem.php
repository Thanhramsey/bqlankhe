<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StockTransactionItem extends Model { protected $fillable=['stock_transaction_id','material_id','quantity','unit_price','amount','note']; protected function casts():array{return ['quantity'=>'decimal:3','unit_price'=>'decimal:2','amount'=>'decimal:2'];} public function material(){return $this->belongsTo(Material::class);} }
