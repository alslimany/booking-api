<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FlightSchedule extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'aero_token_id',
        'uuid',
        'iata',
        'origin',
        'destination',
        'flight_number',
        'aircraft_code',
        'departure',
        'arrival',
        'duration',
    ];

    protected $hidden = [
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $appends = [
        'date',
        'is_international'
    ];

    public function aero_token()
    {
        return $this->belongsTo(AeroToken::class);
    }

    public function availablities()
    {
        return $this->hasMany(FlightAvailablity::class);
    }

    public function one_way_offers()
    {
        return $this->hasMany(OneWayOffer::class);
    }

    public function scopeHasSeats($query)
    {
        return $this->whereHas('availablities', function ($q) {
            $q->where('seats', '>', 0);
        });
    }

    public function scopeHasLowerSeats($query)
    {
        return $this->whereHas('availablities', function ($q) {
            $q->where('seats', '<=', 5);
        });
    }

    public function getDateAttribute()
    {
        return date("Y-m-d", strtotime($this->departure));
    }

    public function getIsInternationalAttribute()
    {

        $airport_from = getAirport($this->origin);
        $airport_to = getAirport($this->destination);
        return ($airport_from->country != $airport_to->country);
    }
}
