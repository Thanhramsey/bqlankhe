<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OperatingDirectiveAttachment extends Model { protected $fillable=['operating_directive_id','original_name','file_path','mime_type','extension','file_size']; public function directive(){return $this->belongsTo(OperatingDirective::class,'operating_directive_id');} }
