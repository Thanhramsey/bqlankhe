<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Neighborhood extends Model { use SoftDeletes; protected $fillable = ['ward_id','code','name']; public function ward(){ return $this->belongsTo(Ward::class); } public function routes(){ return $this->hasMany(CollectionRoute::class); } }
