<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CustomerStoreAccount extends Model {
 protected $guarded=[];
 protected function casts():array{return ['point_balance'=>'integer','total_earned_points'=>'integer','visit_count'=>'integer','purchase_count'=>'integer','total_purchase_amount'=>'decimal:2','first_visited_at'=>'datetime','last_visited_at'=>'datetime','last_purchased_at'=>'datetime'];}
 public function user():BelongsTo{return $this->belongsTo(User::class);}
 public function store():BelongsTo{return $this->belongsTo(Store::class);}
 public function pointTransactions():HasMany{return $this->hasMany(PointTransaction::class);}
}