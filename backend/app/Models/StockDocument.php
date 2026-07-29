<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StockDocument extends Model { protected $fillable=['stock_transaction_id','uploaded_by','original_name','path','mime_type','size']; protected $appends=['url']; public function getUrlAttribute(){return asset('storage/'.$this->path);} }
