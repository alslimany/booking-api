<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoundWayPricing extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'flight_schedule_id',
        'flight_availablity_id',
        'round_way_segment_id',
        'passenger_type',
        'from',
        'to',
        'departure',
        'arrival',
        'cabin',
        'class',
        'fare_basis',
        'fare_price',
        'tax',
        'price',
        'currency',
        'hold_pices',
        'hold_weight',
        'hand_weight',
        'command',
    ];

    protected $hidden = [
        'deleted_at',
        'created_at',
        'updated_at',
        'command',
    ];

    public function flight_availablity()
    {
        return $this->belongsTo(FlightAvailablity::class);
    }
}
