<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FareRuleItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    public function fare_rule()
    {
        return $this->belongsTo(FareRule::class);
    }
}
