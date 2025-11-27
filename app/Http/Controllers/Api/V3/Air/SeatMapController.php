<?php

namespace App\Http\Controllers\Api\V3\Air;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SeatMapController extends Controller
{
    public function get_seat_map(Request $request)
    {
        $request->validate([
            'pnr' => 'required',
            'iata' => 'required',
            'flight_number' => 'required',
            'departure_date' => 'required',
            'origin' => 'required',
            'destination' => 'required',
        ]);

        $aero_token = \App\Models\AeroToken::where('iata', $request->iata)->first();

        $seat_map = $aero_token->build()->seatMap($request->flight_number, $request->departure_date, $request->origin, destination: $request->destination);

        $number_of_rows = 0;
        $number_of_columns = 0;

        foreach ($seat_map['seats'] as $seats) {
            foreach ($seats as $seat) {
                if ($seat['row'] > $number_of_rows) {
                    $number_of_rows = $seat['row'];
                }

                if ($seat['col'] > $number_of_columns) {
                    $number_of_columns = $seat['col'];
                }
            }
        }

        $sorted_seat_map = [];
        for ($i = 1; $i <= $number_of_rows; $i++) {
            foreach ($seat_map['seats'] as $_seats) {
                $firstKey = array_key_first($_seats);
                if ($_seats[$firstKey]['row'] == $i) {
                    $sorted_seat_map[] = $_seats;
                }
            }
        }

        return [
            'meta' => [
                'rows' => $number_of_rows,
                'columns' => $number_of_columns,
                'info' => $seat_map['info'],
            ],
            'data' => $sorted_seat_map,
        ];
    }
}
