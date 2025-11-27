<?php

namespace Database\Seeders;

use App\Models\AeroToken;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AeroTokenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AeroToken::create([
            'name' => 'Libya Airlines',
            'iata' => 'LN',
            'type' => 'amadeus',
            'data' => [
                'api_key' => 'HOzlOP5kpqQORORpAXGouphgkXadfJjl',
                'api_secret' => 'TI4Suueo5yE8kB9M',
            ],
        ]);

        AeroToken::create([
            'name' => 'Oya Airlines',
            'iata' => 'OYA',
            'type' => 'videcom',
            'data' => [
                'api_token' => 'yckp/pLtfavOMp0y98Qe8XvrDX0cjxLDz6e5igXW5qY=',
            ],
        ]);
    }
}
