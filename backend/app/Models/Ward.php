<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Ward extends Model { use SoftDeletes; protected $fillable = ['province_id','code','name']; public function province(){ return $this->belongsTo(Province::class); } public function neighborhoods(){ return $this->hasMany(Neighborhood::class); } }
