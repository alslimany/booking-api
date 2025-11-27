<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FlightAvailablity extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'flight_schedule_id',
        'carrier',
        'name',
        'display_name',
        'cabin',
        'class',
        'is_international',
        'seats',
        'price',
        'currency',
        'tax',
        'miles',
        'fare_available',
        'fare_id',
        'aircraft_code',
        'rules',
    ];

    protected $hidden = [
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $appends = [
        'aero_token_id',
    ];

    protected $casts = [
        'rules' => 'json',
    ];

    public function flight_schedule()
    {
        return $this->belongsTo(FlightSchedule::class);
    }

    public function one_way_offers()
    {
        return $this->hasMany(OneWayOffer::class);
    }

    public function getAeroTokenIdAttribute()
    {
        return $this->flight_schedule->aero_token_id;
    }
}
