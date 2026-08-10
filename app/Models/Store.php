<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
class Store extends Model {
 use SoftDeletes; protected $guarded=[];
 protected function casts():array{return ['reservation_enabled'=>'boolean','is_active'=>'boolean','latitude'=>'decimal:7','longitude'=>'decimal:7'];}
 public function members():HasMany{return $this->hasMany(StoreMember::class);}
 public function images():HasMany{return $this->hasMany(StoreImage::class)->orderBy('sort_order');}
 public function businessHours():HasMany{return $this->hasMany(StoreBusinessHour::class)->orderBy('day_of_week');}
 public function closures():HasMany{return $this->hasMany(StoreClosure::class);}
 public function seats():HasMany{return $this->hasMany(StoreSeat::class);}
 public function reservationSlots():HasMany{return $this->hasMany(ReservationSlot::class);}
 public function reservations():HasMany{return $this->hasMany(Reservation::class);}
 public function noshowPolicy():HasOne{return $this->hasOne(StoreNoshowPolicy::class);}
 public function menuCategories():HasMany{return $this->hasMany(MenuCategory::class);}
 public function menus():HasMany{return $this->hasMany(Menu::class);}
 public function orders():HasMany{return $this->hasMany(Order::class);}
 public function inventories():HasMany{return $this->hasMany(Inventory::class);}
 public function customerAccounts():HasMany{return $this->hasMany(CustomerStoreAccount::class);}
 public function customerVisits():HasMany{return $this->hasMany(CustomerVisit::class);}
 public function coupons():HasMany{return $this->hasMany(Coupon::class);}
 public function postCategories():HasMany{return $this->hasMany(PostCategory::class);}
 public function posts():HasMany{return $this->hasMany(Post::class);}
 public function tags():HasMany{return $this->hasMany(Tag::class);}
 public function reviews():HasMany{return $this->hasMany(Review::class);}
}
