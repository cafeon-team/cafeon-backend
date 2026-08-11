<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MembershipStampEvent extends Model { public $timestamps=false; protected $fillable=['user_id','store_id','order_id','amount','reason']; protected function casts():array{return ['created_at'=>'datetime'];} }
