<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
class MenuIngredient extends Pivot {
 protected $table='menu_ingredients'; public $incrementing=false; protected $guarded=[];
 protected function casts():array{return ['required_quantity'=>'decimal:3'];}
 public function menu():BelongsTo{return $this->belongsTo(Menu::class);}
 public function inventory():BelongsTo{return $this->belongsTo(Inventory::class);}
}