<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'total_price',
        'status'
    ];
    public function casts()
    {
        return ['total_price' => 'decimal:2'];
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')->withPivot(['quantity', 'price', 'subtotal']);
    }
}
