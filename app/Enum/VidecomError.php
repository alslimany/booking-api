<?php
namespace App\Enum;

enum VidecomError: string
{
   case NO_FARE_AVAILABLE = "no_fare_available";
   case NO_FLIGHT_AVAILABLE = "no_flight_available";
   case SEGMENT_ALREADY_EXISTS = "segment_already_exists";
}
