<?php

namespace App\Http\Controllers\Api\V5\Air;

use App\Http\Controllers\Controller;
use App\Models\AeroToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Client;
use App\Services\FlightOfferTransformer;
use Exception;
use SimpleXMLElement;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class FlighOfferSearchController extends Controller
{

    public function flight_offers(Request $request)
    {
        $request->validate([
            'origin_location_code' => 'required',
            'destination_location_code' => 'required',
            'departure_date' => 'required|after_or_equal:' . date('Y-m-d'),
            'return_date' => 'sometimes|after_or_equal:departure_date',
            'adults' => 'required',
            'children' => 'sometimes',
            'infants' => 'sometimes',
            'seated_infants' => 'sometimes',
            'travel_class' => 'sometimes',
            'cheapest_only' => 'sometimes|boolean',
            'only' => 'required|array'
        ]);

        if ($request->filled('return_date')) {
            return $this->round_flight_offers($request);
        } else {
            return $this->oneway_flight_offers($request);
        }
    }

    private function oneway_flight_offers(Request $request)
    {
        $journeys = $this->fetchAndParseAvailability($request);
        if (empty($journeys)) {
            return [];
        }

        $client = new Client();
        $pricingPromises = [];
        $flightsToPrice = [];
        $passengerMixString = $this->getPassengerMixString($request);
        $cacheKeys = [];

        $requestedTravelClass = strtolower($request->input('travel_class', 'economy'));
        $cabinCodeMap = ['economy' => 'Y', 'business' => 'C', 'premium' => 'P', 'first' => 'F'];
        $targetCabinCode = $cabinCodeMap[$requestedTravelClass] ?? 'Y';

        foreach ($journeys as $journey) {
            $aeroToken = AeroToken::find($journey->aero_token_id);
            if (!$aeroToken) continue;
            
            $classes = is_array($journey->Legs->BookFlightSegmentType->Availability->Class)
                ? $journey->Legs->BookFlightSegmentType->Availability->Class
                : [$journey->Legs->BookFlightSegmentType->Availability->Class];

            foreach ($classes as $class) {
                $classAttrs = $class->{'@attributes'};
                $classCode = $classAttrs->id;
                $seatCount = (int)$classAttrs->av;
                $cabinType = $classAttrs->cab;

                if ($cabinType === $targetCabinCode && $seatCount > 0) {
                    $flightSignature = "{$journey->Legs->BookFlightSegmentType->FlightNumber}@{$journey->XSDDepartureDateTime}";
                    $cacheKey = md5("oneway_{$flightSignature}_{$classCode}_{$passengerMixString}");
                    
                    // ** CACHE CHECK **
                    if (Cache::has($cacheKey)) {
                        Log::info("CACHE HIT for one-way: {$cacheKey}");
                        $cachedPnr = Cache::get($cacheKey);
                        FlightOfferTransformer::addPricingData($cacheKey, $cachedPnr, [$journey]);
                        continue;
                    }

                    $pricingCommand = $this->buildPricingCommand([$journey], $request, [$classCode]);
                    Log::info("CACHE MISS. Pricing Command for one-way: " . $pricingCommand);

                    $promise = $aeroToken->build()->getAsyncCommandRunner($pricingCommand);
                    if ($promise) {
                        $cacheKeys[$cacheKey] = $cacheKey;
                        $pricingPromises[$cacheKey] = $client->sendAsync($promise);
                        $flightsToPrice[$cacheKey] = [$journey];
                    }
                }
            }
        }
        
        if (!empty($pricingPromises)) {
            $pricingResults = Utils::settle($pricingPromises)->wait();
            foreach ($pricingResults as $priceableUnitId => $_result) {
                if ($_result['state'] === 'fulfilled') {
                    $responseContent = $_result['value']->getBody()->getContents();
                    $pnrXml = $this->extractInnerXml($responseContent);
                    if ($pnrXml) {
                        $plainPnrObject = json_decode(json_encode($pnrXml));
                        // ** STORE IN CACHE **
                        Cache::put($priceableUnitId, $plainPnrObject, now()->addHours(1));
                        FlightOfferTransformer::addPricingData($priceableUnitId, $plainPnrObject, $flightsToPrice[$priceableUnitId]);
                    } else {
                        Log::warning('Invalid XML for pricing response', ['body' => $responseContent]);
                    }
                }
            }
        }

        if ($request->boolean('cheapest_only')) {
            $flightOffers = FlightOfferTransformer::transformToCheapest();
        } else {
            $flightOffers = FlightOfferTransformer::transform();
        }
        
        return $flightOffers;
    }

    private function round_flight_offers(Request $request)
    {
        $outboundJourneys = $this->fetchAndParseAvailability($request);
        $inboundRequest = clone $request;
        $inboundRequest->merge([
            'origin_location_code' => $request->destination_location_code,
            'destination_location_code' => $request->origin_location_code,
            'departure_date' => $request->return_date,
        ]);
        $inboundJourneys = $this->fetchAndParseAvailability($inboundRequest);

        if (empty($outboundJourneys) || empty($inboundJourneys)) {
            Log::warning('Round trip search failed: No available journeys for outbound or inbound legs.');
            return [];
        }

        $client = new Client();
        $pricingPromises = [];
        $flightsToPrice = [];
        $passengerMixString = $this->getPassengerMixString($request);
        $cacheKeys = [];
        
        $requestedTravelClass = strtolower($request->input('travel_class', 'economy'));
        $cabinCodeMap = ['economy' => 'Y', 'business' => 'C', 'premium' => 'P', 'first' => 'F'];
        $targetCabinCode = $cabinCodeMap[$requestedTravelClass] ?? 'Y';

        foreach ($outboundJourneys as $outboundJourney) {
            $outboundFlightSignature = "{$outboundJourney->Legs->BookFlightSegmentType->FlightNumber}@{$outboundJourney->XSDDepartureDateTime}";
            foreach ($inboundJourneys as $inboundJourney) {
                if ($outboundJourney->aero_token_id !== $inboundJourney->aero_token_id) continue;

                $inboundFlightSignature = "{$inboundJourney->Legs->BookFlightSegmentType->FlightNumber}@{$inboundJourney->XSDDepartureDateTime}";
                
                $outboundClasses = is_array($outboundJourney->Legs->BookFlightSegmentType->Availability->Class) ? $outboundJourney->Legs->BookFlightSegmentType->Availability->Class : [$outboundJourney->Legs->BookFlightSegmentType->Availability->Class];
                $inboundClasses = is_array($inboundJourney->Legs->BookFlightSegmentType->Availability->Class) ? $inboundJourney->Legs->BookFlightSegmentType->Availability->Class : [$inboundJourney->Legs->BookFlightSegmentType->Availability->Class];

                foreach ($outboundClasses as $outClass) {
                    $outAttrs = $outClass->{'@attributes'};
                    if (($outAttrs->cab !== $targetCabinCode) || ((int)$outAttrs->av === 0)) continue;

                    foreach ($inboundClasses as $inClass) {
                        $inAttrs = $inClass->{'@attributes'};
                        if (($inAttrs->cab !== $targetCabinCode) || ((int)$inAttrs->av === 0)) continue;
                        
                        $aeroToken = AeroToken::find($outboundJourney->aero_token_id);
                        if (!$aeroToken) continue;

                        $journeys = [$outboundJourney, $inboundJourney];
                        $classCodes = [$outAttrs->id, $inAttrs->id];

                        $cacheKey = md5("roundtrip_{$outboundFlightSignature}_{$outAttrs->id}_{$inboundFlightSignature}_{$inAttrs->id}_{$passengerMixString}");
                        
                        // ** CACHE CHECK **
                        if (Cache::has($cacheKey)) {
                            Log::info("CACHE HIT for round-trip: {$cacheKey}");
                            $cachedPnr = Cache::get($cacheKey);
                            FlightOfferTransformer::addPricingData($cacheKey, $cachedPnr, $journeys);
                            continue;
                        }

                        $pricingCommand = $this->buildPricingCommand($journeys, $request, $classCodes);
                        Log::info("CACHE MISS. Pricing Round-Trip Command: " . $pricingCommand);
                        
                        $promise = $aeroToken->build()->getAsyncCommandRunner($pricingCommand);
                        if ($promise) {
                            $cacheKeys[$cacheKey] = $cacheKey;
                            $pricingPromises[$cacheKey] = $client->sendAsync($promise);
                            $flightsToPrice[$cacheKey] = $journeys;
                        }
                    }
                }
            }
        }

        if (!empty($pricingPromises)) {
            $pricingResults = Utils::settle($pricingPromises)->wait();
            foreach ($pricingResults as $priceableUnitId => $_result) {
                if ($_result['state'] === 'fulfilled') {
                    $responseContent = $_result['value']->getBody()->getContents();
                    $pnrXml = $this->extractInnerXml($responseContent);
                    if ($pnrXml) {
                        $plainPnrObject = json_decode(json_encode($pnrXml));
                        // ** STORE IN CACHE **
                        Cache::put($priceableUnitId, $plainPnrObject, now()->addHours(1));
                        FlightOfferTransformer::addPricingData($priceableUnitId, $plainPnrObject, $flightsToPrice[$priceableUnitId]);
                    } else {
                        Log::warning('Invalid XML for pricing response', ['body' => $responseContent]);
                    }
                }
            }
        }
        
        return FlightOfferTransformer::transformRoundTrip();
    }

    private function fetchAndParseAvailability(Request $request): array
    {
        $client = new Client();
        $promises = [];
        $tokenIds = $request->input('only', []);
        $origin = $request->input('origin_location_code');
        $destination = $request->input('destination_location_code');
        
        $aeroTokens = AeroToken::findMany($tokenIds);

        foreach ($aeroTokens as $aeroToken) {
            $managementType = $aeroToken->data['airport_management_type'] ?? 'none';
            if ($managementType === 'include') {
                $includedAirports = $aeroToken->getMeta('included_airports', []);
                if (!in_array($origin, $includedAirports) && !in_array($destination, $includedAirports)) {
                    Log::warning("Token {$aeroToken->id} skipped: Route {$origin}-{$destination} not in its include list.");
                    continue;
                }
            } elseif ($managementType === 'exclude') {
                if ($aeroToken->isAirportExecluded($origin) || $aeroToken->isAirportExecluded($destination)) {
                    Log::warning("Token {$aeroToken->id} skipped: Route {$origin}-{$destination} contains an excluded airport.");
                    continue;
                }
            }
            
            $command = "A" . date("dM", strtotime($request->departure_date)) . $origin . $destination . "~x";
            $_request = $aeroToken->build()->getAsyncCommandRunner($command);
            Log::info("Requesting Flights for Token ID {$aeroToken->id} ({$aeroToken->name}) with command: " . $command);
            if ($_request) {
                $promises[$aeroToken->id] = $client->sendAsync($_request);
            }
        }

        $results = Utils::settle($promises)->wait();
        $uniqueJourneys = [];
        $journeyCounter = 0;
        $requestedDate = date('Y-m-d', strtotime($request->departure_date));

        foreach ($results as $aeroTokenId => $_result) {
            if ($_result['state'] === 'fulfilled') {
                $responseContent = $_result['value']->getBody()->getContents();
                $availabilityXml = $this->extractInnerXml($responseContent);

                if ($availabilityXml && isset($availabilityXml->Journeys->Journey)) {
                    foreach ($availabilityXml->Journeys->Journey as $journey) {
                        $departureDateTime = (string)$journey->XSDDepartureDateTime;
                        $flightDepartureDate = date('Y-m-d', strtotime($departureDateTime));
                        
                        if ($flightDepartureDate !== $requestedDate) {
                            continue;
                        }

                        $flightNumber = (string)$journey->Legs->BookFlightSegmentType->FlightNumber;
                        $signature = "{$flightNumber}@{$departureDateTime}";

                        if (!isset($uniqueJourneys[$signature])) {
                            $plainJourneyObject = json_decode(json_encode($journey));
                            $plainJourneyObject->id = 'journey_' . $journeyCounter++;
                            $plainJourneyObject->aero_token_id = $aeroTokenId;
                            $uniqueJourneys[$signature] = $plainJourneyObject;
                        }
                    }
                } else {
                    Log::info('Could not extract valid inner XML or no journeys found', ['body' => $responseContent]);
                }
            }
        }
        return array_values($uniqueJourneys);
    }
    
    private function extractInnerXml(string $soapResponse)
    {
        libxml_use_internal_errors(true);
        try {
            $outerXml = new SimpleXMLElement($soapResponse, LIBXML_NOCDATA);
            $namespaces = $outerXml->getNamespaces(true);
            $soap = $outerXml->children($namespaces['soap']);
            $body = $soap->Body;
            $commandResult = $body->children($namespaces[''] ?? null)[0] ?? null;
            if ($commandResult) {
                $innerXmlString = html_entity_decode((string)$commandResult);
                if (strpos(trim($innerXmlString), '<') === 0) {
                    return new SimpleXMLElement($innerXmlString);
                }
            }
        } catch (Exception $e) { return false; } 
        finally {
            libxml_clear_errors();
            libxml_use_internal_errors(false);
        }
        return false;
    }
    
    private function buildPricingCommand(array $journeys, Request $request, array $classCodes): string
    {
        $adults = $request->input('adults', 1);
        $children = $request->input('children', 0);
        $infants = $request->input('infants', 0);
        $seatedInfants = $request->input('seated_infants', 0);
        $totalPassengers = $adults + $children + $infants + $seatedInfants;
        $seatCount = $adults + $children + $seatedInfants;
        
        $paxManifest = [];
        $paxCounter = 0;
        $paxChar = 65; // ASCII for 'A'
        
        for ($i = 0; $i < $adults; $i++) { $paxManifest[] = "/" . chr($paxChar + $paxCounter++) . "#"; }
        for ($i = 0; $i < $children; $i++) { $paxManifest[] = "/" . chr($paxChar + $paxCounter++) . "#.CH08"; }
        for ($i = 0; $i < $infants; $i++) { $paxManifest[] = "/" . chr($paxChar + $paxCounter++) . "#.IN06"; }
        for ($i = 0; $i < $seatedInfants; $i++) { $paxManifest[] = "/" . chr($paxChar + $paxCounter++) . "#.IS06"; }

        $paxCommandString = "i^-{$totalPassengers}pax" . implode('', $paxManifest) . "/^0";
        
        $flightCommandSegments = [];
        foreach ($journeys as $index => $journey) {
            $classCode = $classCodes[$index];
            $bookingStatus = 'NN';
            $flightSegment = $journey->Legs->BookFlightSegmentType->FlightNumber;
            $date = date("dM", strtotime($journey->XSDDepartureDateTime));
            $origin = $journey->DepartureAirport->{'@attributes'}->LocationCode;
            $destination = $journey->ArrivalAirport->{'@attributes'}->LocationCode;
            $flightCommandSegments[] = "{$flightSegment}{$classCode}{$date}{$origin}{$destination}{$bookingStatus}{$seatCount}";
        }
        $flightCommandString = implode('^0', $flightCommandSegments);

        return "{$paxCommandString}{$flightCommandString}^FG^FS1^*r~x";
    }

    /**
     * Helper to generate a consistent string for the passenger mix.
     */
    private function getPassengerMixString(Request $request): string
    {
        $adults = $request->input('adults', 0);
        $children = $request->input('children', 0);
        $infants = $request->input('infants', 0);
        $seatedInfants = $request->input('seated_infants', 0);
        return "A{$adults}-C{$children}-I{$infants}-S{$seatedInfants}";
    }
}