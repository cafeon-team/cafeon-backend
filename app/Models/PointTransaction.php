<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PointTransaction extends Model {
 public $timestamps=false; const UPDATED_AT=null; protected $guarded=[];
 protected function casts():array{return ['amount'=>'integer','balance_after'=>'integer','expires_at'=>'datetime','created_at'=>'datetime'];}
 public function account():BelongsTo{return $this->belongsTo(CustomerStoreAccount::class,'customer_store_account_id');}
 public function creator():BelongsTo{return $this->belongsTo(User::class,'created_by');}
}