<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StoreFavorite extends Model { protected $fillable=['user_id','store_id']; public function store(){return $this->belongsTo(Store::class);} }
