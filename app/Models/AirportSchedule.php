<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AirportSchedule extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $hidden = ['id'];
    protected $fillable = [
        'ref_id',
        'type',
        'number',
        'airline_iata',
        'status',
        'status_at',
        'aircraft',
        'origin',
        'destination',
        'scheduled_departure_at',
        'scheduled_arrival_at',
    ];

    protected $appends = [
        'airline',
    ];

    public function getAirlineAttribute()
    {
        return \App\Models\Airline::where('iata', $this->airline_iata)->first();
    }
}
