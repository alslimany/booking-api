<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    use HasFactory;

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
