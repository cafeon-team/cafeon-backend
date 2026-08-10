<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomerVisit extends Model {
 public $timestamps=false; const UPDATED_AT=null; protected $guarded=[];
 protected function casts():array{return ['visited_at'=>'datetime','points_awarded'=>'boolean','created_at'=>'datetime'];}
 public function user():BelongsTo{return $this->belongsTo(User::class);}
 public function store():BelongsTo{return $this->belongsTo(Store::class);}
 public function reservation():BelongsTo{return $this->belongsTo(Reservation::class);}
 public function order():BelongsTo{return $this->belongsTo(Order::class);}
 public function confirmer():BelongsTo{return $this->belongsTo(User::class,'confirmed_by');}
}