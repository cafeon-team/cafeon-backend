<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class Inventory extends Model {
 use SoftDeletes; protected $guarded=[];
 protected function casts():array{return ['quantity'=>'decimal:3','low_stock_threshold'=>'decimal:3','is_active'=>'boolean'];}
 public function store():BelongsTo{return $this->belongsTo(Store::class);}
 public function creator():BelongsTo{return $this->belongsTo(User::class,'created_by');}
 public function updater():BelongsTo{return $this->belongsTo(User::class,'updated_by');}
 public function transactions():HasMany{return $this->hasMany(InventoryTransaction::class);}
 public function menus():BelongsToMany{return $this->belongsToMany(Menu::class,'menu_ingredients')->withPivot('required_quantity')->withTimestamps();}
}