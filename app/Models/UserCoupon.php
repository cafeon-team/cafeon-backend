<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class UserCoupon extends Model {
 protected $guarded=[];
 protected function casts():array{return ['issued_at'=>'datetime','expires_at'=>'datetime','used_at'=>'datetime','cancelled_at'=>'datetime'];}
 public function coupon():BelongsTo{return $this->belongsTo(Coupon::class);}
 public function user():BelongsTo{return $this->belongsTo(User::class);}
 public function issuer():BelongsTo{return $this->belongsTo(User::class,'issued_by');}
 public function usedOrder():BelongsTo{return $this->belongsTo(Order::class,'used_order_id');}
}