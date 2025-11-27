<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FareRule extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'aero_token_id',
        'carrier',
        'fare_id',
        'rules',
        'note',
        'status',

    ];

    protected $casts = [
        'rules' => 'json',
        'note' => 'json',
    ];

    public function aero_token()
    {
        return $this->belongsTo(AeroToken::class);
    }

    public function items()
    {
        return $this->hasMany(FareRuleItem::class);
    }
}
