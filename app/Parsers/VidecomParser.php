<?php
namespace App\Parsers;

use DateTime;
use SimpleXMLElement;

class VidecomParser {
    public static function parseAvailabilityXml(string $xmlResponse): array {
        $xml = new SimpleXMLElement($xmlResponse);
        $result = [];

        foreach ($xml->Journeys->Journey as $journey) {
            $legs = [];
            
            foreach ($journey->Legs->BookFlightSegmentType as $segment) {
                $availability = [];
                foreach ($segment->Availability->Class as $class) {
                    $availability[] = [
                        'class' => (string)$class['id'],
                        'seats_available' => (int)$class['av'],
                        'cabin' => (string)$class['cab']
                    ];
                }

                $legs[] = [
                    'flight_number' => (string)$segment->FlightNumber,
                    'departure' => [
                        'airport_code' => (string)$segment->DepartureAirport['LocationCode'],
                        'airport_name' => (string)$segment->DepartureAirport['LocationName'],
                        'datetime' => new DateTime((string)$segment->XSDDepartureDateTime)
                    ],
                    'arrival' => [
                        'airport_code' => (string)$segment->ArrivalAirport['LocationName'],
                        'airport_name' => (string)$segment->ArrivalAirport['LocationName'],
                        'datetime' => new DateTime((string)$segment->XSDArrivalDateTime)
                    ],
                    'equipment' => [
                        'type' => (string)$segment->Equipment['AirEquipType']
                    ],
                    'duration' => (string)$segment->FlightDuration,
                    'availability' => $availability
                ];
            }

            $result[] = [
                'departure_airport' => [
                    'code' => (string)$journey->DepartureAirport['LocationCode'],
                    'name' => (string)$journey->DepartureAirport['LocationName'],
                    'city_code' => (string)$journey->DepartureAirportCityCode
                ],
                'arrival_airport' => [
                    'code' => (string)$journey->ArrivalAirport['LocationCode'],
                    'name' => (string)$journey->ArrivalAirport['LocationName'],
                    'city_code' => (string)$journey->ArrivalAirportCityCode
                ],
                'departure_date' => new DateTime((string)$journey->XSDDepartureDateTime),
                'legs' => $legs
            ];
        }

        return $result;
    }

    public static function parseAvailabilityJson(string $jsonResponse): array {
        $data = json_decode($jsonResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::error('Invalid JSON response', ['error' => json_last_error_msg()]);
            return [];
        }

        $result = [];

        foreach ($data['Journeys'] ?? [] as $journey) {
            try {
                $departureAirport = $journey['DepartureAirport'] ?? [];
                $arrivalAirport = $journey['ArrivalAirport'] ?? [];
                $legs = [];

                foreach ($journey['Legs'] ?? [] as $leg) {
                    $legs[] = self::parseFlightLeg($leg);
                }

                $result[] = [
                    'departure_airport' => [
                        'code' => $departureAirport['LocationCode'] ?? '',
                        'name' => $departureAirport['LocationName'] ?? '',
                        'city_code' => $journey['DepartureAirportCityCode'] ?? ''
                    ],
                    'arrival_airport' => [
                        'code' => $arrivalAirport['LocationCode'] ?? '',
                        'name' => $arrivalAirport['LocationName'] ?? '',
                        'city_code' => $journey['ArrivalAirportCityCode'] ?? ''
                    ],
                    'departure_date' => self::parseDateTime($journey['XSDDepartureDateTime'] ?? ''),
                    'legs' => $legs
                ];
            } catch (\Exception $e) {
                \Log::error('Error parsing journey', ['error' => $e->getMessage()]);
                continue;
            }
        }

        return $result;
    }

    protected static function parseFlightLeg(array $leg): array {
        return [
            'flight_number' => $leg['FlightNumber'] ?? '',
            'departure' => self::parseAirportTiming(
                $leg['DepartureAirport'] ?? [],
                $leg['XSDDepartureDateTime'] ?? ''
            ),
            'arrival' => self::parseAirportTiming(
                $leg['ArrivalAirport'] ?? [],
                $leg['XSDArrivalDateTime'] ?? ''
            ),
            'equipment' => [
                'type' => $leg['Equipment']['AirEquipType'] ?? ''
            ],
            'duration' => $leg['FlightDuration'] ?? '',
            'availability' => self::parseAvailabilityClasses($leg['Availability'] ?? [])
        ];
    }

    protected static function parseAirportTiming(array $airport, string $xsdDateTime): array {
        return [
            'airport_code' => $airport['LocationCode'] ?? '',
            'airport_name' => $airport['LocationName'] ?? '',
            'datetime' => self::parseDateTime($xsdDateTime)
        ];
    }

    protected static function parseAvailabilityClasses(array $classes): array {
        return array_map(function ($class) {
            return [
                'class' => $class['id'] ?? '',
                'seats_available' => (int) ($class['av'] ?? 0),
                'cabin' => $class['cab'] ?? ''
            ];
        }, $classes);
    }

    protected static function parseDateTime(string $xsdDateTime): ?DateTime {
        try {
            return new DateTime($xsdDateTime);
        } catch (\Exception $e) {
            \Log::warning('Invalid date format', ['input' => $xsdDateTime]);
            return null;
        }
    }
}