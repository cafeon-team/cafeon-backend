<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Referral extends Model { protected $fillable=['inviter_id','invitee_id','code','status','reward_points','completed_at']; protected function casts():array{return ['completed_at'=>'datetime'];} }
