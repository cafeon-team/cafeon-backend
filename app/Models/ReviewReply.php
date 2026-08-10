<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ReviewReply extends Model {
 protected $guarded=[];
 public function review():BelongsTo{return $this->belongsTo(Review::class);}
 public function author():BelongsTo{return $this->belongsTo(User::class,'author_id');}
}