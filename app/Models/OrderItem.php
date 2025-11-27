<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'type',
        'provider',
        'reference',
        'price',
        'taxes',
        'total',
        'currency_code',
        'exchange_rate',
        'item',
        'net_commission',
        'agent_commission',
        'remaning',
        'paid',
        'status',
        'issued_at',
    ];

    protected $casts = [
        'item' => 'json',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // public function order_item_sales()
    // {
    //     return $this->hasMany(OrderItemSale::class);
    // }

    // public function getAmountProduct(Customer $customer): int|string
    // {
    //     return $this->attributes['total'];
    // }

    // public function getMetaProduct(): ?array
    // {
    //     return [
    //         'title' => $this->type,
    //         'description' => 'Purchase of ' . $this->type . ' #' . $this->reference,
    //     ];
    // }
}
