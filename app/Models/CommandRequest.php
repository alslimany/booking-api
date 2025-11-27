<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommandRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'aero_token_id',
        'user_id',
        'command',
        'result',
    ];

    protected $appends = [
        'created_at_formatted',
    ];

    public function aero_token()
    {
        return $this->belongsTo(AeroToken::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    protected function getCreatedAtFormattedAttribute()
    {
        return $this->created_at->format('H:i d, M y');
    }
}
