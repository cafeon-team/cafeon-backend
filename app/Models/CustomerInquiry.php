<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerInquiry extends Model { protected $fillable=['user_id','category','title','content','status','answer','answered_by','answered_at']; protected function casts():array{return ['answered_at'=>'datetime'];} public function user(){return $this->belongsTo(User::class);} }
