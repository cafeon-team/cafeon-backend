<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ReviewImage extends Model {
 public $timestamps=false; const UPDATED_AT=null; protected $guarded=[];
 protected function casts():array{return ['sort_order'=>'integer','created_at'=>'datetime'];}
 public function review():BelongsTo{return $this->belongsTo(Review::class);}
}