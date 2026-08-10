<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class InventoryTransaction extends Model {
 public $timestamps=false; const UPDATED_AT=null; protected $guarded=[];
 protected function casts():array{return ['quantity'=>'decimal:3','quantity_before'=>'decimal:3','quantity_after'=>'decimal:3','created_at'=>'datetime'];}
 public function inventory():BelongsTo{return $this->belongsTo(Inventory::class);}
 public function creator():BelongsTo{return $this->belongsTo(User::class,'created_by');}
}