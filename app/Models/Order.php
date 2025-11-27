<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Order extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'number',
        'owner_type',
        'owner_id',
        'status',
        'issued_at',
    ];
    
    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }
    public function contact_documents()
    {
        return $this->hasManyThrough(ContactDocument::class, Contact::class);
    }
    public function order_items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function owner()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
