<?php

namespace App\Models;

use App\Core\Hotel3tn;
use App\Core\IHotel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Zoha\Metable;

class HotelToken extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Metable;

    public $fillable = [
        'name',
        'code',
        'type',
        'data',
        'user_id',
        'provider_id',
    ];

    public $casts = [
        'data' => 'array',
    ];

    public function build(): IHotel
    {
        $serrvice = null;
        if ($this->type == '3t') {
            $serrvice = new Hotel3tn($this);
        }

        return $serrvice;

    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function getUuid()
    {
        return strtoupper($this->code . $this->id);
    }

}
