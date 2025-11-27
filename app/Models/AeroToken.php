<?php

namespace App\Models;

use App\Core\Amadeus;
use App\Core\ICore;
use App\Core\Videcom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Http;
use Zoha\Metable;

class AeroToken extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Metable;

    public $fillable = [
        'name',
        'iata',
        'type',
        'data',
        'user_id',
    ];

    public $hidden = [
        // 'type',
        'user_id',
        // 'data',
        'created_at',
        'deleted_at',
        'updated_at',
    ];
    public $casts = [
        'data' => 'array',
    ];

    public function build(): ICore
    {
        if ($this->type == 'amadeus') {
            $amadeus = new Amadeus($this);

            return $amadeus;
        }

        if ($this->type == 'videcom') {
            $videcom = new Videcom($this);

            return $videcom;
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function flight_schedules()
    {
        return $this->hasMany(FlightSchedule::class);
    }

    public function getQueueId()
    {
        return 'AT-' . $this->iata . '-' . str_replace(' ', '', $this->name);
    }

    public function getExecludedAirports()
    {
        return isset($this->data['execluded_airports']) ? $this->data['execluded_airports'] : [];
    }

    public function isAirportExecluded($airport)
    {
        foreach ($this->getExecludedAirports() as $a) {
            if (strtoupper($a) == strtoupper($airport)) {
                return true;
            }
        }
        return false;
        // return in_array($airport, $this->getExecludedAirports());
    }
    public function isAirportNotExecluded($airport)
    {
        foreach ($this->getExecludedAirports() as $a) {
            if (strtoupper($a) == strtoupper($airport)) {
                return false;
            }
        }
        return true;
        // return in_array($airport, $this->getExecludedAirports());
    }

    public function scopeWithFlightRoute($query, string $origin, string $destination)
    {
        return $query->whereHas('flight_schedules', function ($q) use ($origin, $destination) {
            $q->where(function ($subQuery) use ($origin, $destination) {
                $subQuery->where([
                    'origin' => strtoupper($origin),
                    'destination' => strtoupper($destination)
                ])->orWhere([
                    'origin' => strtoupper($destination),
                    'destination' => strtoupper($origin)
                ]);
            });
        });
    }
}
