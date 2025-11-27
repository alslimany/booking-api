<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoundWaySegment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'round_way_offer_id',
        'flight_schedule_id',
        'type',
        'from',
        'to',
        'departure',
        'arrival',
        'carrier',
        'flight_number',
        'duration',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function round_way_offer()
    {
        return $this->belongsTo(RoundWayOffer::class);
    }

    public function flight_schedule()
    {
        return $this->belongsTo(FlightSchedule::class);
    }

    public function round_way_pricings()
    {
        return $this->hasMany(RoundWayPricing::class);
    }
}
