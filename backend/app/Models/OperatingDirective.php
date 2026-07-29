<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class OperatingDirective extends Model { use SoftDeletes; protected $fillable=['created_by','code','title','content','is_active']; protected function casts():array{return ['is_active'=>'boolean'];} public function creator(){return $this->belongsTo(User::class,'created_by');} public function recipients(){return $this->belongsToMany(User::class,'operating_directive_recipient')->withPivot('read_at')->withTimestamps();} public function attachments(){return $this->hasMany(OperatingDirectiveAttachment::class);} }
