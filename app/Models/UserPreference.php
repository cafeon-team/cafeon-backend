<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserPreference extends Model { protected $fillable=['user_id','order_notifications','location_enabled','marketing_notifications','latitude','longitude','preferred_tags']; protected function casts():array{return ['order_notifications'=>'boolean','location_enabled'=>'boolean','marketing_notifications'=>'boolean','preferred_tags'=>'array','latitude'=>'decimal:7','longitude'=>'decimal:7'];} }
