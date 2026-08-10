<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class Coupon extends Model {
 use SoftDeletes; protected $guarded=[];
 protected function casts():array{return ['discount_value'=>'decimal:2','minimum_order_amount'=>'decimal:2','maximum_discount_amount'=>'decimal:2','valid_from'=>'datetime','valid_until'=>'datetime','total_quantity'=>'integer','issued_quantity'=>'integer','per_user_limit'=>'integer','is_active'=>'boolean'];}
 public function store():BelongsTo{return $this->belongsTo(Store::class);}
 public function creator():BelongsTo{return $this->belongsTo(User::class,'created_by');}
 public function freeMenu():BelongsTo{return $this->belongsTo(Menu::class,'free_menu_id');}
 public function userCoupons():HasMany{return $this->hasMany(UserCoupon::class);}
}