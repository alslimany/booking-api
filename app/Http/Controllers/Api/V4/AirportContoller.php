<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AirportContoller extends Controller
{
    /**
     * 
     * List all airports
     * @return JsonResponse
     * 
     */
    public function list(): JsonResponse
    {
        return response()->json([], 200);
    }

    public function list_arrivals($airport): JsonResponse
    {

        $files = [
            'dom_arrivals' => '/www/wwwroot/ftp/travsys/domarr.txt',
            'dom_departures' => '/www/wwwroot/ftp/travsys/domdep.txt',
            'int_arrivals' => '/www/wwwroot/ftp/travsys/intarr.txt',
            'int_departures' => '/www/wwwroot/ftp/travsys/intdep.txt',
        ];


        $arrivals = [];
        $departures = [];

        foreach ($files as $key => $filePath) {
            $fileContents = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($fileContents as $line) {
                $fields = explode(',', $line);
                // $flightData = [
                //     'type' => trim($fields[0]),
                //     'code' => trim($fields[1]),
                //     'flight_number' => trim($fields[2]),
                //     'airline' => trim($fields[3]),
                //     'airport' => trim($fields[4]),
                //     'time' => trim($fields[5]),
                //     'date' => trim($fields[9]),
                //     'category' => strpos($key, 'dom') !== false ? 'domestic' : 'international', // Determine the category
                // ];

                $flightData = [
                    'flight' => strpos($key, 'dom') !== false ? 'domestic' : 'international', // Domestic or International
                    'airline_iata_code' => trim($fields[1]), // Airline IATA code
                    'flight_number' => (int) trim($fields[2]), // Flight number as an integer
                    'airport' => trim($fields[3]), // Airport name
                    'time' => substr(trim($fields[5]), 0, 2) . ':' . substr(trim($fields[5]), 2, 2), // Time formatted as HH:MM
                    'date' => date('d-m-Y', strtotime(str_replace('.', '-', trim($fields[9])))), // Date formatted as DD-MM-YYYY
                    'status' => trim($fields[8] ?? ''), // Flight status (e.g., Checkin, Canceled)
                ];        

                if (strpos($key, 'arrivals') !== false) {
                    $arrivals[] = $flightData;
                } elseif (strpos($key, 'departures') !== false) {
                    $departures[] = $flightData;
                }
            }
        }
        return response()->json([
            'arrivals' => $arrivals,
            'departures' => $departures,
        ], 200);
    }

    public function list_departures($airport): JsonResponse
    {
        return response()->json([], 200);
    }
}
