<?php

namespace App\Services;

class FlightOfferTransformer
{
    private static $pricedOffers = [];

    public static function addPricingData(string $priceableUnitId, \stdClass $pnr, array $journeys)
    {
        self::$pricedOffers[$priceableUnitId] = [
            'pnr' => $pnr,
            'journeys' => $journeys,
        ];
    }
    
    public static function transform(): array
    {
        $finalOffers = [];
        $offerId = 1;

        foreach (self::$pricedOffers as $priceableUnitId => $data) {
            $pnr = $data['pnr'];
            $journey = $data['journeys'][0];
            $segment = $journey->Legs->BookFlightSegmentType;
            $pnrItin = is_array($pnr->Itinerary->Itin) ? $pnr->Itinerary->Itin[0] : $pnr->Itinerary->Itin;
            $soldClass = $pnrItin->{'@attributes'}->Class;
            
            $bookableSeats = 0;
            $classes = is_array($segment->Availability->Class) ? $segment->Availability->Class : [$segment->Availability->Class];
            foreach ($classes as $class) {
                if ($class->{'@attributes'}->id === $soldClass) {
                    $bookableSeats = (int)$class->{'@attributes'}->av;
                    break;
                }
            }
            
            $arrivalDateStr = trim($pnrItin->{'@attributes'}->ArrOfst) ?: $pnrItin->{'@attributes'}->DepDate;
            $arrivalDateTime = date('Y-m-d\TH:i:s', strtotime($arrivalDateStr . ' ' . $pnrItin->{'@attributes'}->ArrTime));

            $finalOffers[] = [
                'type' => 'flight-offer', 'id' => (string)($offerId++), 'source' => 'GDS', 'oneWay' => true,
                'aero_token_id' => $journey->aero_token_id,
                'lastTicketingDate' => '2025-11-30', 'numberOfBookableSeats' => $bookableSeats,
                'itineraries' => [[
                    'duration' => self::formatDuration($segment->FlightDuration),
                    'segments' => [[
                        'departure' => ['iataCode' => $segment->DepartureAirport->{'@attributes'}->LocationCode, 'at' => $segment->XSDDepartureDateTime,],
                        'arrival' => ['iataCode' => $segment->ArrivalAirport->{'@attributes'}->LocationCode, 'at' => $arrivalDateTime,],
                        'carrierCode' => $pnrItin->{'@attributes'}->AirID, 'number' => $pnrItin->{'@attributes'}->FltNo,
                        'aircraft' => [ 'code' => $segment->Equipment->{'@attributes'}->AirEquipType ],
                        'duration' => self::formatDuration($segment->FlightDuration),
                        'id' => (string)$pnrItin->{'@attributes'}->Line, 'numberOfStops' => (int)$pnrItin->{'@attributes'}->Stops,
                    ]]
                ]],
                'price' => [
                    'currency' => self::getCurrency($pnr->FareQuote),
                    'total' => self::findTotal($pnr->FareQuote), 'base' => self::calculateBasePrice($pnr->FareQuote),
                ],
                'travelerPricings' => self::buildTravelerPricings($pnr),
            ];
        }

        self::$pricedOffers = [];
        usort($finalOffers, fn($a, $b) => (float)$a['price']['total'] <=> (float)$b['price']['total']);
        return $finalOffers;
    }

    public static function transformToCheapest(): array 
    { 
        return []; 
    }

    public static function transformRoundTrip(): array
    {
        if (empty(self::$pricedOffers)) {
            return [];
        }

        $finalOffers = [];
        $offerId = 1;

        foreach (self::$pricedOffers as $priceableUnitId => $data) {
            $pnr = $data['pnr'];
            $journeys = $data['journeys']; 

            // Robustness check for valid PNR structure
            if (!isset($pnr->Itinerary->Itin)) continue;
            $pnrItins = is_array($pnr->Itinerary->Itin) ? $pnr->Itinerary->Itin : [$pnr->Itinerary->Itin];

            if (count($pnrItins) < 2) continue;

            $itineraries = [];
            foreach ($journeys as $index => $journey) {
                if (!isset($pnrItins[$index])) continue;
                $segment = $journey->Legs->BookFlightSegmentType;
                $pnrItin = $pnrItins[$index];

                $arrivalDateStr = trim($pnrItin->{'@attributes'}->ArrOfst) ?: $pnrItin->{'@attributes'}->DepDate;
                $arrivalDateTime = date('Y-m-d\TH:i:s', strtotime($arrivalDateStr . ' ' . $pnrItin->{'@attributes'}->ArrTime));

                $itineraries[] = [
                    'duration' => self::formatDuration($segment->FlightDuration),
                    'segments' => [[
                        'departure' => ['iataCode' => $segment->DepartureAirport->{'@attributes'}->LocationCode, 'at' => $segment->XSDDepartureDateTime],
                        'arrival' => ['iataCode' => $segment->ArrivalAirport->{'@attributes'}->LocationCode, 'at' => $arrivalDateTime],
                        'carrierCode' => $pnrItin->{'@attributes'}->AirID, 'number' => $pnrItin->{'@attributes'}->FltNo,
                        'aircraft' => ['code' => $segment->Equipment->{'@attributes'}->AirEquipType],
                        'duration' => self::formatDuration($segment->FlightDuration),
                        'id' => (string)$pnrItin->{'@attributes'}->Line,
                        'numberOfStops' => (int)$pnrItin->{'@attributes'}->Stops,
                    ]]
                ];
            }

            $finalOffers[] = [
                'type' => 'flight-offer', 'id' => (string)($offerId++), 'source' => 'GDS', 'oneWay' => false,
                'aero_token_id' => $journeys[0]->aero_token_id,
                'lastTicketingDate' => '2025-11-30',
                'numberOfBookableSeats' => (int)$pnrItins[0]->{'@attributes'}->PaxQty,
                'itineraries' => $itineraries,
                'price' => [
                    'currency' => self::getCurrency($pnr->FareQuote),
                    'total' => self::findTotal($pnr->FareQuote), 'base' => self::calculateBasePrice($pnr->FareQuote),
                ],
                'travelerPricings' => self::buildTravelerPricings($pnr),
            ];
        }

        self::$pricedOffers = [];
        usort($finalOffers, fn($a, $b) => (float)$a['price']['total'] <=> (float)$b['price']['total']);
        return $finalOffers;
    }
    
    private static function getCurrency($fareQuote): string
    {
        if (!isset($fareQuote->FareStore)) return 'N/A';
        $fareStores = is_array($fareQuote->FareStore) ? $fareQuote->FareStore : [$fareQuote->FareStore];
        return $fareStores[0]->{'@attributes'}->Cur ?? 'N/A';
    }

    private static function findTotal($fareQuote): string
    {
        if (!isset($fareQuote->FareStore)) return '0.00';
        $fareStores = is_array($fareQuote->FareStore) ? $fareQuote->FareStore : [$fareQuote->FareStore];
        foreach ($fareStores as $fareStore) {
            if (isset($fareStore->{'@attributes'}->FSID) && $fareStore->{'@attributes'}->FSID === 'Total') {
                return $fareStore->{'@attributes'}->Total;
            }
        }
        return '0.00';
    }

    private static function calculateBasePrice($fareQuote): string
    {
        if (!isset($fareQuote->FareStore)) return '0.00';
        $base = 0;
        $fareStores = is_array($fareQuote->FareStore) ? $fareQuote->FareStore : [$fareQuote->FareStore];
        foreach ($fareStores as $fareStore) {
            if (isset($fareStore->{'@attributes'}->FSID) && $fareStore->{'@attributes'}->FSID === 'FQC') {
                if (isset($fareStore->SegmentFS)) {
                    $segmentFS = is_array($fareStore->SegmentFS) ? $fareStore->SegmentFS : [$fareStore->SegmentFS];
                    foreach($segmentFS as $segment) {
                         $base += (float)$segment->{'@attributes'}->Fare;
                    }
                }
            }
        }
        return number_format($base, 2, '.', '');
    }

    private static function buildTravelerPricings($pnr): array
    {
        if (!isset($pnr->FareQuote->FareStore) || !isset($pnr->Names->PAX)) return [];
        
        $travelerPricings = [];
        $paxDetails = [];
        $paxList = is_array($pnr->Names->PAX) ? $pnr->Names->PAX : [$pnr->Names->PAX];
        foreach ($paxList as $pax) {
            $paxDetails[$pax->{'@attributes'}->PaxNo] = ['type' => $pax->{'@attributes'}->PaxType];
        }
        
        $fareStores = is_array($pnr->FareQuote->FareStore) ? $pnr->FareQuote->FareStore : [$pnr->FareQuote->FareStore];
        foreach ($fareStores as $fareStore) {
            $attrs = $fareStore->{'@attributes'};
            if (!isset($attrs->FSID) || $attrs->FSID !== 'FQC') continue;
            
            $paxNo = $attrs->Pax;
            if (!isset($paxDetails[$paxNo])) continue;
            $paxType = $paxDetails[$paxNo]['type'];

            $segmentFS = is_array($fareStore->SegmentFS) ? $fareStore->SegmentFS : [$fareStore->SegmentFS];
            $basePrice = 0;
            foreach($segmentFS as $segment) {
                $basePrice += (float)$segment->{'@attributes'}->Fare;
            }

            $travelerPricings[] = [
                'travelerId' => $paxNo, 'fareOption' => 'STANDARD', 'travelerType' => self::mapPaxType($paxType),
                'price' => [
                    'currency' => $attrs->Cur, 'total' => $attrs->Total,
                    'base' => number_format($basePrice, 2, '.', ''),
                ],
            ];
        }
        return $travelerPricings;
    }
    
    private static function mapPaxType(string $type): string
    {
        return match ($type) {
            'AD' => 'ADULT', 'CH' => 'CHILD', 'IN' => 'INFANT', 'IS' => 'INFANT',
            default => strtoupper($type),
        };
    }
    
    private static function formatDuration(string $duration): string
    {
        preg_match('/(\d+)h\s*(\d+)?/', $duration, $matches);
        $hours = $matches[1] ?? 0;
        $minutes = $matches[2] ?? 0;
        return "PT{$hours}H{$minutes}M";
    }
}