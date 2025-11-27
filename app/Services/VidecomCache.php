<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use SimpleXMLElement;

class VidecomCache
{
    /**
     * Get an item from the cache.
     */
    public static function get(string $key)
    {
        return Cache::get($key);
    }

    /**
     * Store an item in the cache.
     * @param int $minutes TTL in minutes. Defaults to 15.
     */
    public static function put(string $key, $value, int $minutes = 15)
    {
        Cache::put($key, $value, now()->addMinutes($minutes));
    }



    /**
     * Generates a cache key for the main availability search.
     */
    public static function getAvailabilityCacheKey(Request $request): string
    {
        return implode('_', [
            'availability',
            $request->origin_location_code,
            $request->destination_location_code,
            date("Ymd", strtotime($request->departure_date)),
            // You might want to add passenger counts here if it affects availability
        ]);
    }

    /**
     * Generates a cache key for a specific flight's pricing.
     * THE FIX IS HERE: The type hint is changed from SimpleXMLElement to \stdClass
     */
    public static function getPricingCacheKey(\stdClass $journey, Request $request): string
    {
        $flightNumber = $journey->Legs->BookFlightSegmentType->FlightNumber;
        $departureTime = $journey->XSDDepartureDateTime;

        // The price can change based on the number and type of passengers.
        $paxHash = md5(json_encode($request->passengers ?? [])); // Assuming passengers are in the request

        return implode('_', [
            'price',
            $flightNumber,
            $departureTime,
            $paxHash
        ]);
    }
}