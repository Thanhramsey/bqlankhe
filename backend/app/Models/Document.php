<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Document extends Model { use SoftDeletes; protected $fillable=['document_category_id','created_by','code','name','document_date','file_path','original_name','extension','mime_type','file_size','description','is_active']; protected function casts():array{return ['document_date'=>'date:Y-m-d','is_active'=>'boolean'];} public function category(){return $this->belongsTo(DocumentCategory::class,'document_category_id');} public function creator(){return $this->belongsTo(User::class,'created_by');} }
