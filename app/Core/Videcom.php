<?php

namespace App\Core;

use App\Jobs\StoreCommandRequestJob;
use App\Models\AeroToken;
use GuzzleHttp\RedirectMiddleware;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RicorocksDigitalAgency\Soap\Facades\Soap;

// 
use GuzzleHttp\Client;
use Meng\AsyncSoap\Guzzle\Factory;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\StreamFactory;

// 
class Videcom implements ICore
{
    private $aeroToken;
    private $url;
    private $mode;
    private $client;

    public function __construct(AeroToken $aero)
    {
        $this->aeroToken = $aero;
        $this->url = $aero->data['url'];
        $this->mode = $aero?->data['mode'] ?? 'api';
    }

    private function _get_if_exists($array, $key)
    {
        if (is_array($array) && array_key_exists($key, $array)) {
            return $array[$key];
        }

        return "0";
    }

    public function flightDatesAvialability($data)
    {
        $from = $data['from'];
        $to = $data['to'];
        $date = $data['date'];

        $key = "flight_dates_avialability_" . $from . "_" . $to . "_" . $date;

        $result = cache()->per($key, 60, function () use ($from, $to, $date) {
            $command = "A" . date('dM', strtotime($date)) . $from . $to . "~x";

            $response = $this->runCommand($command);

            $result = $response?->response;

            if ($result != null) {
                $xml = $result;

                $xmlObject = simplexml_load_string($xml);

                // $jsonFormatData = json_encode($xmlObject);
                // $result = json_decode($jsonFormatData, true);

                $days = [];
                for ($i = 0; $i < 7; $i++) {
                    $day_date = date('Y-m-d', strtotime($date . '+' . $i . 'days'));
                    $days[$day_date] = [
                        'is_avialable' => false,
                        'from' => $from,
                        'to' => $to,
                        'departure_date' => $day_date,
                        'flight_number' => "",
                        'carrierCode' => [],
                    ];
                }

                foreach ($xmlObject->Journeys->Journey as $row) {
                    $departure_date = date('Y-m-d', strtotime($row->DepartureDate . ''));
                    if (array_key_exists($departure_date, $days)) {
                        $days[$departure_date] = [
                            'is_avialable' => true,
                            'from' => $row->DepartureAirportCityCode . '',
                            'to' => $row->ArrivalAirportCityCode . '',
                            // 'departure_date' => date('Y-m-d H:i', strtotime($row['DepartureDate'])),
                            'departure_date' => date('Y-m-d', strtotime($row->DepartureDate . '')),
                            'flight_number' => $row->Legs->BookFlightSegmentType->FlightNumber . '',
                        ];

                        // $days[$departure_date] = $row
                    }
                    // $availability = [
                    //     'from' => $row['DepartureAirportCityCode'],
                    //     'to' => $row['ArrivalAirportCityCode'],
                    //     // 'departure_date' => date('Y-m-d H:i', strtotime($row['DepartureDate'])),
                    //     'departure_date' => date('Y-m-d', strtotime($row['DepartureDate'])),
                    //     'flight_number' => $row['Legs']['BookFlightSegmentType']['FlightNumber'],
                    // ];

                    // $avilabilities[] = $availability;
                }

                return $days;

                // return $avilabilities;
            }
        });

        return $result;
    }

    public function info()
    {
        $command = "ZUA~x";

        $response = $this->runCommand($command);

        $result = $response?->response;


        if ($result != null) {

            $xml = "<xml>" . $result . "</xml>";


            $xmlObject = simplexml_load_string($xml);

            $jsonFormatData = json_encode($xmlObject);
            $result = json_decode($jsonFormatData, true);

            // return $result;

            return [
                'aero_token_id' => $this->aeroToken->id,
                'iata' => $this->aeroToken->iata,
                'office_code' => $result['zua']['officecode'],
                'office_name' => $result['zua']['officename'],
                'show_fares' => $result['zua']['showfares'],
                'balance' => [
                    'limit' => $result['zua']['ticketmoneylimit']['@attributes']['limit'],
                    'currency' => $result['zua']['ticketmoneylimit']['@attributes']['cur'],
                    'status' => $result['zua']['ticketmoneylimit']['@attributes']['Status'],
                ],
            ];
        }
    }

    public function flightAvialability($data)
    {
        $from = $data['from'];
        $to = $data['to'];
        $date = $data['date'];

        $command = "A" . date('dM', strtotime($date)) . $from . $to . "~x";

        $response = $this->runCommand(($command));

        $result = $response?->response;

        if ($result != null) {
            $xml = $result;

            $xmlObject = simplexml_load_string($xml);

            // $jsonFormatData = json_encode($xmlObject);
            // $result = json_decode($jsonFormatData, true);

            $avilabilities = [];

            foreach ($xmlObject->Journeys->Journey as $row) {
                $availability = [
                    'from' => $row->DepartureAirportCityCode . "",
                    'to' => $row->ArrivalAirportCityCode . "",
                    // 'departure_date' => date('Y-m-d H:i', strtotime($row['DepartureDate'])),
                    'departure_date' => date('Y-m-d', strtotime($row->DepartureDate . "", )),
                    'flight_number' => $row->Legs->BookFlightSegmentType->FlightNumber . '',
                    'duration' => $row->Legs->BookFlightSegmentType->FlightDuration . '',
                    'seats' => [],
                ];

                foreach ($row->Legs->BookFlightSegmentType->Availability->Class as $class) {
                    $availability['seats'][] = [
                        'cabine' => $class->attributes('', true)->cab . "",
                        'class' => $class->attributes('', true)->id . "",
                        'count' => $class->attributes('', true)->av . "",
                    ];
                }

                $avilabilities[] = $availability;
            }

            return $avilabilities;
        }
    }

    public function findOneWayFlights($data)
    {
        $adults = $data['adults'];
        $children = $data['children'];
        $infants = $data['infants'];
        $departure = $data['departure'];
        $from = $data['from'];
        $to = $data['to'];
        $currency = "LYD";

        $number_of_seats = $adults + $children;// => Infants didn't take up seats + $infants;

        $command = "A" . date('dM', strtotime($departure)) . $from . $to . "[SalesCity=" . $from . ",ClassBands=True,VARS=True,QTYSEATS=" . $number_of_seats . ",SingleSeg=s,FGNoAv=True,QuoteCurrency=" . $currency . "]";

        $response = $this->runCommand($command);

        $result = $response?->response;
        // if ($response->ok()) {  

        $flight_offer = null;
        $flight_offers = [];

        if ($result != null) {
            $xml = "";

            if (preg_match('/<xml>(.*?)<\/xml>/', $result, $match) == 1) {
                $xml = $match[1];
            }

            $xml = "<xml>" . $xml . "</xml>";

            $xmlObject = simplexml_load_string($xml);

            $flight_offer_id = 1;
            foreach ($xmlObject?->itin as $videcom_itinirary) {
                $bookable_seats = 9;
                $flight_offer_segment_id = 1;
                $segments = [];

                $flight_offer = [
                    'uuid' => md5("flightoffer" . $flight_offer_id . $this->aeroToken->iata),
                    "type" => "flight-offer",
                    "id" => $flight_offer_id,
                    'international' => $videcom_itinirary->attributes('', true)->international == 1 ? true : false,
                    "source" => "videcom",
                    "oneWay" => true,
                    "lastTicketingDate" => "",
                    "numberOfBookableSeats" => "",
                    'itineraries' => [],
                ];

                $itiniraries = [];

                foreach ($videcom_itinirary->flt as $videcom_flight) {

                    $uuid_key = $videcom_flight->fltdet->airid . "_" . $videcom_flight->dep . "_" . $videcom_flight->time->ddaylcl . "T" . $videcom_flight->time->dtimlcl
                        . "_" . $videcom_flight->arr . "_" . $videcom_flight->time->adaylcl . "T" . $videcom_flight->time->atimlcl;
                    $segment = [
                        'uuid' => md5($uuid_key),
                        'id' => $flight_offer_segment_id,
                        'departure' => [
                            'iataCode' => "" . $videcom_flight->dep,
                            'terminal' => "" . $videcom_flight->fltdet->depterm,
                            'at' => $videcom_flight->time->ddaylcl . "T" . $videcom_flight->time->dtimlcl,
                        ],
                        'arrival' => [
                            'iataCode' => "" . $videcom_flight->arr,
                            'terminal' => "" . $videcom_flight->fltdet->arrterm,
                            'at' => $videcom_flight->time->adaylcl . "T" . $videcom_flight->time->atimlcl,
                        ],
                        'carrierCode' => "" . $videcom_flight->fltdet->airid,
                        'number' => "" . $videcom_flight->fltdet->fltno,
                        'aircraft' => [
                            'code' => "" . $videcom_flight->fltdet->eqp,
                        ],
                        'operating' => [
                            'carrierCode' => "" . $videcom_flight->fltdet->airid,
                        ],
                        'duration' => "" . $videcom_flight->time->duration,
                        'numberOfStops' => "" . $videcom_flight->fltdet->stp,
                    ];

                    $flight_offer_segment_id++;

                    $segment_pricing = $this->priceSegment($segment, $adults, $children, $infants);

                    $segment['offers'] = $segment_pricing;

                    // return [$segment];
                    foreach ($segment_pricing as $s_p) {
                        if ($bookable_seats > $s_p['seats']) {
                            $bookable_seats = $s_p['seats'];
                        }
                    }

                    $segments[] = $segment;
                }

                $itiniraries[] = [
                    'duration' => "" . $videcom_itinirary->attributes('', true)->totalduration,
                    'segments' => $segments,
                ];

                $flight_offer['numberOfBookableSeats'] = $bookable_seats;

                $flight_offer['itineraries'] = $itiniraries;
                $flight_offer_id++;

                $flight_offer['travelerPricings'] = [];

                $traveler_index = 1;
                $travelerPricings = [];
                for ($i = 0; $i < $adults; $i++) {
                    $travelerPricings[] = [
                        'travelerId' => $traveler_index,
                        'fareOption' => 'STANDARD',
                        'travelerType' => 'ADULT',
                        'price' => [
                            "currency" => "EUR",
                            "total" => "355.34",
                            "base" => "255.00"
                        ],
                        'fareDetailsBySegment' => []
                    ];

                    $traveler_index++;
                }

                // for ($i = 0; $i < $offer['_meta']['passengers']['children']; $i++) {
                //     $offer['travelers'][] = [
                //         'index' => $traveler_index,
                //         'type' => 'CHILD',
                //         'name' => '',
                //     ];
                //     $traveler_index++;
                // }

                // for ($i = 0; $i < $offer['_meta']['passengers']['infants']; $i++) {
                //     $offer['travelers'][] = [
                //         'index' => $traveler_index,
                //         'type' => 'INFANT',
                //         'name' => '',
                //     ];
                //     $traveler_index++;
                // }

                $flight_offer['price'] = [
                    "currency" => "",
                    "total" => "355.34",
                    "base" => "255.00",
                    "fees" => [
                        [
                            "amount" => "0.00",
                            "type" => "SUPPLIER",
                        ],
                        [
                            "amount" => "0.00",
                            "type" => "TICKETING"
                        ],
                    ],
                    "grandTotal" => "355.34"
                ];
                // 
                array_push($flight_offers, $flight_offer);
            }
        }

        return $flight_offers;

        return [
            'type' => 'line',
            'origin' => $from,
            'destination' => $to,
            'departure' => $departure,
            'arrival' => $departure,
            'flight_offers' => $flight_offers,
        ];
    }

    public function findFlights($data)
    {
        $adults = $data['adults'];
        $children = $data['children'];
        $infants = $data['infants'];
        $departure = $data['departure'];
        $return = $data['return'];
        $from = $data['from'];
        $to = $data['to'];
        $currency = "LYD";

        $number_of_seats = $adults + $children + $infants;

        $flights = $this->aeroToken->flight_schedules()->with('availablities')
            ->where([
                'origin' => $from,
                'destination' => $to,
            ])
            ->whereDate('departure', date('Y-m-d', strtotime($departure)))
            ->get();


        return $flights;
    }

    public function findRoundFlights($data)
    {
        # A09JUNMJISEB[SALESCITY=MJI,VARS=TRUE,CLASSBANDS=TRUE,STARTCITY=MJI,QTYSEATS=1,SINGLESEG=r,FGNOAV=TRUE,JOURNEY=MJI-SEB-MJI,RETURN=12JUN2022]
        # A12JUNSEBMJI[SALESCITY=SEB,VARS=TRUE,CLASSBANDS=TRUE,STARTCITY=MJI,QTYSEATS=1,SINGLESEG=r,FGNOAV=TRUE,JOURNEY=MJI-SEB-MJI,DEPART=09JUN2022]

        $depart_command = "";
        $return_command = "";

        $adults = $data['adults'];
        $children = $data['children'];
        $infants = $data['infants'];
        $departure = $data['departure'];
        $return = $data['return'];
        $from = $data['from'];
        $to = $data['to'];
        $currency = "LYD";

        $number_of_seats = $adults + $children + $infants;

        $flights = collect();

        $result1 = $this->aeroToken->flight_schedules()->with('availablities')
            ->where([
                'origin' => $from,
                'destination' => $to,
            ])
            ->whereDate('departure', date('Y-m-d', strtotime($departure)))
            ->get();

        $result2 = $this->aeroToken->flight_schedules()->with('availablities')
            ->where([
                'origin' => $to,
                'destination' => $from,
            ])
            ->whereDate('departure', date('Y-m-d', strtotime($return)))
            ->get();

        $flights->push(...$result1);
        $flights->push(...$result2);

        return $flights;
    }

    public function findMultistopFlights($data)
    {
        $origin_destinations = $data['origin_destinations'];
        $adults = $data['adults'];
        $children = $data['children'];
        $infants = $data['infants'];

        $flights = collect();
        if (count($origin_destinations) == 1) {
            if (array_key_exists('return', $origin_destinations[0])) {
                $line = $origin_destinations[0];

                $result1 = $this->aeroToken->flight_schedules()->with('availablities')
                    ->where([
                        'origin' => $line['from'],
                        'destination' => $line['to'],
                    ])
                    ->whereDate('departure', $line['departure'])
                    ->get();

                $result2 = $this->aeroToken->flight_schedules()->with('availablities')
                    ->where([
                        'origin' => $line['to'],
                        'destination' => $line['from'],
                    ])
                    ->whereDate('departure', $line['return'])
                    ->get();

                $flights->push(...$result1);
                $flights->push(...$result2);

                return $flights;
            }
        }
        foreach ($origin_destinations as $line) {
            $result = $this->aeroToken->flight_schedules()->with('availablities')
                ->where([
                    'origin' => $line['from'],
                    'destination' => $line['to'],
                ])
                ->whereDate('departure', $line['departure'])
                ->get();

            $flights->push(...$result);
        }

        return $flights;

    }

    public function schedule($data)
    {
        $command = "";
        switch ($this->aeroToken->iata) {
            case 'YI':
                $command = "ssrpfltschedule/" . $data['from'] . "/" . $data['to'] . "[z/]";
                break;
            case 'YL':
                $command = "ssrpfltschedule/" . $data['from'] . "/" . $data['to'] . "[z/]";
                break;
            case 'UZ':
                // $command = "ssrpfltscheduleall[z/]";
                $command = "ssrpschdl/" . $data['from'] . "/" . $data['to'] . "[z/]";
                break;
            default:
                $command = "ssrpfltschedule/" . $data['from'] . "/" . $data['to'] . "[z/]";
                break;
        }

        $response = $this->runCommand($command);

        $result = $response?->response;


        if ($result != null) {
            $xml = "";

            if (preg_match('/<xml>(.*?)<\/xml>/', $result, $match) == 1) {
                $xml = $match[1];
            }

            $xml = "<xml>" . $xml . "</xml>";

            // return $xml;
            $xmlObject = simplexml_load_string($xml);

            // $jsonFormatData = json_encode($xmlObject);
            // $result = json_decode($jsonFormatData, true);

            $schedule = [];
            $index = 1;
            // if (array_key_exists('fltschedule', $result)) {
            switch ($this->aeroToken->iata) {
                case 'YI':
                    foreach ($xmlObject->fltschedule as $item) {
                        $schedule[] = $this->parseFlightScheule($index, $item);
                        $index++;
                    }
                    break;
                case 'UZ':
                    foreach ($xmlObject->schdl as $item) {
                        $schedule[] = $this->parseFlightScheule($index, $item);
                        $index++;
                    }
                    break;
                default:
                    foreach ($xmlObject->fltschedule as $item) {
                        $schedule[] = $this->parseFlightScheule($index, $item);
                        $index++;
                    }
                    break;
            }
            // }


            return $schedule;
        }

    }

    public function web_schedule($data)
    {
        if (date('Y-m-d') > date('Y-m-d', strtotime($data['date']))) {
            return [];
        }

        $command = "A" . date('dM', strtotime($data['date'])) . $data['from'] . $data['to'] . '~X';

        $response = $this->runCommand($command);

        $result = $response?->response;


        if ($result != null) {

            $xmlObject = simplexml_load_string($result);

            // $jsonFormatData = json_encode($xmlObject);
            // $result = json_decode($jsonFormatData, true);

            $schedule = [];
            $index = 1;
            // if (array_key_exists('fltschedule', $result)) {
            foreach ($xmlObject->Journeys?->Journey as $journey) {
                if (!$this->aeroToken->getMeta('execluded_airports')?->contains($journey->DepartureAirportCityCode . '') && !$this->aeroToken->getMeta('execluded_airports')?->contains($journey->ArrivalAirportCityCode . '')) {
                    // if ($this->aeroToken->isAirportNotExecluded($journey->DepartureAirportCityCode . '') && $this->aeroToken->isAirportNotExecluded($journey->ArrivalAirportCityCode . '')) {
                    $schedule[] = [
                        'index' => $index,
                        'iata' => $this->aeroToken->iata,
                        'flight_number' => $journey->Legs->BookFlightSegmentType->FlightNumber . '',
                        'flight_date' => date('Y-m-d', strtotime(str_replace('/', '-', $journey->DepartureDate . ''))),
                        'departure' => [
                            'airport' => $journey->DepartureAirportCityCode . '',
                            'datetime' => date('Y-m-d H:i', strtotime(str_replace('/', '-', $journey->DepartureDate . ''))),
                        ],
                        'arrival' => [
                            'airport' => $journey->ArrivalAirportCityCode . '',
                            'datetime' => date('Y-m-d H:i', strtotime(str_replace('/', '-', $journey->Legs->BookFlightSegmentType->ArrivalDateTime . ''))),
                        ],
                        'carrier' => [
                            'number' => $journey->Legs->BookFlightSegmentType->Equipment->attributes('', true)->AirEquipType . '',
                        ],
                        '@no_pax' => '',
                        'sale' => true,
                    ];

                    $index++;
                }
            }


            return $schedule;
        }

    }

    public function salesReport($data)
    {
        $command = "sr/" . date('dMY', strtotime($data['from'])) . "/" . date('dMY', strtotime($data['to'])) . "[c/sr.csv]";

        $response = $this->runCommand($command);

        $result = $response?->response;

        return $result;

        if ($result != null) {
            $xml = "";

            if (preg_match('/<xml>(.*?)<\/xml>/', $result, $match) == 1) {
                $xml = $match[1];
            }

            $xml = "<xml>" . $xml . "</xml>";

            $xmlObject = simplexml_load_string($xml);

            $jsonFormatData = json_encode($xmlObject);
            $result = json_decode($jsonFormatData, true);

            $schedule = [];
            $index = 1;
            if (array_key_exists('fltschedule', $result)) {
                foreach ($result['fltschedule'] as $item) {
                    $schedule[] = [
                        'index' => $index,
                        'iata' => $this->aeroToken->iata,
                        'flight_number' => $item['FLTNO'],
                        'flight_date' => date('Y-m-d', strtotime(str_replace('/', '-', $item['Flt_Date']))),
                        'departure' => [
                            'airport' => $item['DEPART'],
                            'datetime' => date('Y-m-d H:i', strtotime(str_replace('/', '-', $item['LVTIME']))),
                        ],
                        'arrival' => [
                            'airport' => $item['DESTIN'],
                            'datetime' => date('Y-m-d H:i', strtotime(str_replace('/', '-', $item['ARTIME']))),
                        ],
                        'carrier' => [
                            'number' => $item['REF'],
                        ],
                        '@no_pax' => $item['NoPAX'],
                        'sale' => !$item['RestrictSales'],
                    ];

                    $index++;
                }
            }


            return $schedule;
        }

    }

    public function flightContacts($data)
    {
        switch ($this->aeroToken->iata) {
            case 'YI':
                $command = "ssrpLCContact/" . $data['flight_number'] . "/" . date('dMy', strtotime($data['flight_date'])) . "[z/]";
                // $command = "ch" . $data['flight_number'] . "/" . date('dM', strtotime($data['flight_date'])) . "/" . $data['airport'];

                $response = $this->runCommand($command);

                $result = $response?->response;

                if ($result != null) {
                    $xml = "";

                    if (preg_match('/<xml>(.*?)<\/xml>/', $result, $match) == 1) {
                        $xml = $match[1];
                    }

                    $xml = "<xml>" . $xml . "</xml>";

                    $xmlObject = simplexml_load_string($xml);

                    $jsonFormatData = json_encode($xmlObject);
                    $result = json_decode($jsonFormatData, true);

                    $contacts = [];
                    $index = 0;
                    if (isset($result['LCContact'])) {
                        foreach ($result['LCContact'] as $contact) {
                            $phone = "";
                            if ($contact['MobilePhone'] != null) {
                                $phone = $contact['MobilePhone'];
                            } elseif ($contact['HomePhone'] != null) {
                                $phone = $contact['HomePhone'];
                            } elseif ($contact['BusinessPhone'] != null) {
                                $phone = $contact['BusinessPhone'];
                            } elseif ($contact['TravelPhone'] != null) {
                                $phone = $contact['TravelPhone'];
                            }

                            // Start - Phone number correction
                            if (count(explode(' ', $phone)) > 1) {
                                $arr = explode(' ', $phone);

                                // if (count($arr) > 2) {
                                // $phone = $arr[count($arr) - 2];
                                // } else {
                                $phone = $arr[count($arr) - 1];
                                // }
                            }
                            $phone = str_replace('+', '00', $phone);
                            $phone = preg_replace("/[^0-9,.]/", "", $phone);
                            // End - Phone number correction

                            $contacts[] = [
                                'index' => $index++,
                                'pnr' => $contact['PNRNumber'],
                                'flight_number' => $contact['FLTNO'],
                                'flight_date' => $contact['Flt_Date'],
                                'departure' => $contact['DEPART'],
                                'arrival' => $contact['DESTIN'],
                                'class' => $contact['Class'],
                                // 'title' => substr($contact['FirstName'], -2),
                                'first_name' => substr($contact['FirstName'], 0, -2),
                                'last_name' => $contact['LastName'],
                                'mobile_phone' => $phone,
                                'email' => $contact['Email'],
                            ];
                        }
                    }


                    return $contacts;
                }
                break;
            case 'UZ':
                $command = "ssrpLCContact/" . $data['flight_number'] . "/" . date('dM', strtotime($data['flight_date'])) . "[z/]";
                // $command = "ch" . $data['flight_number'] . "/" . date('dM', strtotime($data['flight_date'])) . "/" . $data['airport'];

                $response = $this->runCommand($command);

                $result = $response?->response;

                if ($result != null) {
                    $xml = "";

                    if (preg_match('/<xml>(.*?)<\/xml>/', $result, $match) == 1) {
                        $xml = $match[1];
                    }

                    $xml = "<xml>" . $xml . "</xml>";

                    $xmlObject = simplexml_load_string($xml);

                    $jsonFormatData = json_encode($xmlObject);
                    $result = json_decode($jsonFormatData, true);

                    $contacts = [];
                    $index = 0;

                    foreach ($result['LCContact'] as $contact) {
                        $phone = "";
                        if ($contact['MobilePhone'] != null) {
                            $phone = $contact['MobilePhone'];
                        } elseif ($contact['HomePhone'] != null) {
                            $phone = $contact['HomePhone'];
                        } elseif ($contact['BusinessPhone'] != null) {
                            $phone = $contact['BusinessPhone'];
                        } elseif ($contact['TravelPhone'] != null) {
                            $phone = $contact['TravelPhone'];
                        }

                        // Start - Phone number correction
                        if (count(explode(' ', $phone)) > 1) {
                            $arr = explode(' ', $phone);

                            // if (count($arr) > 2) {
                            // $phone = $arr[count($arr) - 2];
                            // } else {
                            $phone = $arr[count($arr) - 1];
                            // }
                        }
                        $phone = str_replace('+', '00', $phone);
                        $phone = preg_replace("/[^0-9,.]/", "", $phone);
                        // End - Phone number correction

                        $contacts[] = [
                            'index' => $index++,
                            'pnr' => $contact['PNRNumber'],
                            'flight_number' => $contact['FLTNO'],
                            'flight_date' => $contact['Flt_Date'],
                            'departure' => $contact['DEPART'],
                            'arrival' => $contact['DESTIN'],
                            'class' => '',//$contact['Class'],
                            // 'title' => substr($contact['FirstName'], -2),
                            'first_name' => substr($contact['FirstName'], 0, -2),
                            'last_name' => $contact['LastName'],
                            'mobile_phone' => $phone,
                            'email' => $contact['Email'],
                        ];
                    }

                    return $contacts;
                }
                break;
            case 'BM':
                $command = "ssrpLCContact/" . $data['flight_number'] . "/" . date('dM', strtotime($data['flight_date'])) . "[z/]";
                // $command = "ch" . $data['flight_number'] . "/" . date('dM', strtotime($data['flight_date'])) . "/" . $data['airport'];

                $response = $this->runCommand($command);

                $result = $response?->response;

                if ($result != null) {
                    $xml = "";

                    if (preg_match('/<xml>(.*?)<\/xml>/', $result, $match) == 1) {
                        $xml = $match[1];
                    }

                    $xml = "<xml>" . $xml . "</xml>";

                    $xmlObject = simplexml_load_string($xml);

                    $jsonFormatData = json_encode($xmlObject);
                    $result = json_decode($jsonFormatData, true);

                    $contacts = [];
                    $index = 0;

                    foreach ($result['LCContact'] as $contact) {
                        $phone = "";
                        if ($contact['MobilePhone'] != null) {
                            $phone = $contact['MobilePhone'];
                        } elseif ($contact['HomePhone'] != null) {
                            $phone = $contact['HomePhone'];
                        } elseif ($contact['BusinessPhone'] != null) {
                            $phone = $contact['BusinessPhone'];
                        } elseif ($contact['TravelPhone'] != null) {
                            $phone = $contact['TravelPhone'];
                        }

                        // Start - Phone number correction
                        if (count(explode(' ', $phone)) > 1) {
                            $arr = explode(' ', $phone);

                            // if (count($arr) > 2) {
                            // $phone = $arr[count($arr) - 2];
                            // } else {
                            $phone = $arr[count($arr) - 1];
                            // }
                        }
                        $phone = str_replace('+', '00', $phone);
                        $phone = preg_replace("/[^0-9,.]/", "", $phone);
                        // End - Phone number correction

                        $contacts[] = [
                            'index' => $index++,
                            'pnr' => $contact['PNRNumber'],
                            'flight_number' => $contact['FLTNO'],
                            'flight_date' => $contact['Flt_Date'],
                            'departure' => $contact['DEPART'],
                            'arrival' => $contact['DESTIN'],
                            'class' => $contact['Class'],
                            // 'title' => substr($contact['FirstName'], -2),
                            'first_name' => substr($contact['FirstName'], 0, -2),
                            'last_name' => $contact['LastName'],
                            'mobile_phone' => $phone,
                            'email' => $contact['Email'],
                        ];
                    }

                    return $contacts;
                }

                break;

            default:
                $command = "ssrpLCContact/" . $data['flight_number'] . "/" . date('dM', strtotime($data['flight_date'])) . "[z/]";
                // $command = "ch" . $data['flight_number'] . "/" . date('dM', strtotime($data['flight_date'])) . "/" . $data['airport'];

                $response = $this->runCommand($command);

                $result = $response?->response;

                if ($result != null) {
                    $xml = "";

                    if (preg_match('/<xml>(.*?)<\/xml>/', $result, $match) == 1) {
                        $xml = $match[1];
                    }

                    $xml = "<xml>" . $xml . "</xml>";

                    $xmlObject = simplexml_load_string($xml);

                    $jsonFormatData = json_encode($xmlObject);
                    $result = json_decode($jsonFormatData, true);

                    $contacts = [];
                    $index = 0;

                    foreach ($result['LCContact'] as $contact) {
                        $phone = "";
                        if ($contact['MobilePhone'] != null) {
                            $phone = $contact['MobilePhone'];
                        } elseif ($contact['HomePhone'] != null) {
                            $phone = $contact['HomePhone'];
                        } elseif ($contact['BusinessPhone'] != null) {
                            $phone = $contact['BusinessPhone'];
                        } elseif ($contact['TravelPhone'] != null) {
                            $phone = $contact['TravelPhone'];
                        }

                        // Start - Phone number correction
                        if (count(explode(' ', $phone)) > 1) {
                            $arr = explode(' ', $phone);

                            $phone = $arr[count($arr) - 1];
                        }
                        $phone = str_replace('+', '00', $phone);
                        $phone = preg_replace("/[^0-9,.]/", "", $phone);
                        // End - Phone number correction

                        $contacts[] = [
                            'index' => $index++,
                            'pnr' => $contact['PNRNumber'],
                            'flight_number' => $contact['FLTNO'],
                            'flight_date' => $contact['Flt_Date'],
                            'departure' => $contact['DEPART'],
                            'arrival' => $contact['DESTIN'],
                            'class' => '',
                            // 'title' => substr($contact['FirstName'], -2),
                            'first_name' => substr($contact['FirstName'], 0, -2),
                            'last_name' => $contact['LastName'],
                            'mobile_phone' => $phone,
                            'email' => $contact['Email'],
                        ];
                    }

                    return $contacts;
                }

                break;
        }

    }

    public function holdPnr($data)
    {
        /**
         * Command
         * [
         * i^-
         * 3Pax/A#/B#.CH08/C#.IN22^0J22251Y10DEC19GYDNAJNN2^0J20252Y10DEC19NAJGYDNN2^
         * FG^FS1^9c*1234567^8M/20^e*r~x
         * ]
         */

        ## Worked Command
        ## => i^-1Pax/A#^0YI0510Y08MAYMJISEBNN1^FG^FS1^9c*1234567^e*r~x

        $hold_pnr_command = "";

        foreach ($data["passengers"] as $passenger) {
            switch ($passenger["type"]) {
                case "adult":
                    // $hold_pnr_command .= $letter . '#/';
                    $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . '#/';
                    break;
                case "child":
                    // $hold_pnr_command .= $letter . '#.CH10/';
                    $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . '#.CH10/';
                    break;
                case "infant":
                    // $hold_pnr_command .= $letter . '#.IN06/';
                    $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . '#.IN06/';
                    break;
            }
        }

        // $hold_pnr_command .= "^9-" . $data["passengers"][0]['id'] . "M*" . $data['contact']['phone'];
        // $hold_pnr_command .= "^9-" . $data["passengers"][0]['id'] . "E*" . $data['contact']['email'];
        foreach ($data["passengers"] as $passenger) {
            if ($passenger['is_primary_contact']) {
                // $hold_pnr_command .= "^9-" . $passenger['id'] . "M*" . $passenger['phone'];
                $hold_pnr_command .= "^9-" . $passenger['index'] . "M*" . $data['contact']['phone'];
            }
        }

        $hold_pnr_command = substr($hold_pnr_command, 0, -1);

        $count_offers = count($data['offers']);
        $count_passengers = count($data["passengers"]);

        foreach ($data['offers'] as $offer) {
            // $hold_pnr_command .= "^0" . $offer['carrierCode'] . $offer['number'] . $offer['class'] . date('dM', strtotime($offer['departure']['at'])) . $offer['departure']['iataCode'] . $offer['arrival']['iataCode'] . "NN" . $count_passengers . "^9C*123456^E*R~X^FG^FS1";
            $hold_pnr_command .= "^0" . $offer['carrierCode'] . $offer['number'] . $offer['class'] . date('dM', strtotime($offer['departure']['at'])) . $offer['departure']['iataCode'] . $offer['arrival']['iataCode'] . "NN" . $count_passengers;
        }

        // $hold_pnr_command = "I^-" . count($data["passengers"]) . "/" . $hold_pnr_command . "^E*R~X^FG^FS1";
        $hold_pnr_command = "I^" . $hold_pnr_command . "^FG^FS1^E*R~X";
        // return ['cmd' => $hold_pnr_command];
        $response = $this->runCommand($hold_pnr_command);

        $result = $response?->response;

        $pnr = [];

        if ($result != null) {
            $xml = "";

            $xml = "<xml>" . $result . "</xml>";

            $xmlObject = simplexml_load_string($xml);

            $pax_taxes = [];
            foreach ($xmlObject->PNR->FareQuote->FareTax->PaxTax as $tax) {
                $pax_taxes[] = [
                    'segment_id' => $tax->attributes('', true)->Seg . '',
                    'pax_id' => $tax->attributes('', true)->Pax . '',
                    'code' => $tax->attributes('', true)->Code . '',
                    'currency' => $tax->attributes('', true)->Cur . '',
                    'amount' => $tax->attributes('', true)->Amnt . '',
                    'description' => $tax->attributes('', true)->desc . '',
                    'separate' => $tax->attributes('', true)->separate . '',
                ];
            }

            $pnr = [
                'iata' => $this->aeroToken->iata,
                'type' => 'hold',
                'rloc' => $xmlObject->PNR->attributes('', true)->RLOC . "",
                'is_pnr_locked' => $xmlObject->PNR->attributes('', true)->PNRLocked . "",
                'is_pnr_edittable' => $xmlObject->PNR->attributes('', true)->editPNR . "",
                'is_voidable' => $xmlObject->PNR->attributes('', true)->CanVoid . "",

                'total_price' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Total,
                'currency' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Cur,
                'fare_qoute' => [
                    'currency' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Cur,
                    'fqi' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->FQI,
                    'price' => [
                        'total' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Total,
                        'fare' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Fare,
                        'tax1' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax1,
                        'tax2' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax2,
                        'tax3' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax3,
                    ],
                    'taxes' => $pax_taxes,
                ],
                'fare_store' => [
                    'miles' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->miles,
                    'hold_pices' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldPcs,
                    'hold_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldWt,
                    'hand_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HandWt,
                ],
                'itinerary' => [
                    'line' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Line . "",
                    'airline_id' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->AirID . "",
                    'flight_number' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->FltNo . "",
                    'class' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Class . "",
                    'departure' => [
                        'iataCode' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Depart . "",
                        'terminal' => '',
                        'at' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->DepDate . " " . $xmlObject->PNR->Itinerary->Itin->attributes('', true)->DepTime,
                    ],
                    'arrival' => [
                        'iataCode' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Arrive . "",
                        'terminal' => '',
                        'at' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ArrDate . " " . $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ArrTime,
                    ],
                    'status' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Status . "",
                    'number_of_passengers' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->PaxQty . "",
                    // 'ArrOfst' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ArrOfst,
                    'number_of_stops' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Stops . "",
                    'cabine' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Cabin . "",
                    'class_band' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ClassBand . "",
                    'class_band_display_name' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ClassBandDisplayName . "",
                    'online_checkin' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->onlineCheckin . "",
                    'select_seat' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->SelectSeat . "",
                    'mmb_select_seat' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->MMBSelectSeat . "",
                    'is_online_checkin_allowed' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->MMBCheckinAllowed . "",
                ],

                'time_limits' => [
                    'ttl_id' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLID . "",
                    'ttl_city' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLCity . "",
                    'ttl_queue_number' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLQNo . "",
                    'ttl_time' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLTime . "",
                    'ttl_date' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLDate . "",
                    'age_city' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->AgCity . "",
                    'sine_code' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->SineCode . "",
                    'sine_type' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->SineType . "",
                    'reservation_at' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->ResDate . "",
                ],
            ];

            return $pnr;
        }
        // return "I^-" . count($data['passengers']) . "PAX/" . $hold_pnr_command;
        //foreach ()

        //return "i^-" . $index . "Pax/" . $hold_pnr_command . "^0YI0510Y08MAYMJISEBNN1^FG^FS1^9c*1234567^e*r~x"

        // I^-A#/0YI0510C09JunMJISEBNN1^FG^FS1^9C*123456^E*R~X



    }

    public function createHoldedPnr($data)
    {
        $command = "*" . $data['holded_pnr']['rloc'] . "^MI-API " . $data['payment_references'];
        if ($data['with_email']) {
            $command .= "^EZRE";
        }
        $command .= "^EZT*R^*R~X";

        $response = $this->runCommand($command);

        $result = $response?->response;

        $pnr = [];

        if ($result != null) {
            $xml = "";

            $xml = "<xml>" . $result . "</xml>";

            $xmlObject = simplexml_load_string($xml);

            $pax_taxes = [];
            foreach ($xmlObject->PNR->FareQuote->FareTax->PaxTax as $tax) {
                $pax_taxes[] = [
                    'segment_id' => $tax->attributes('', true)->Seg . '',
                    'pax_id' => $tax->attributes('', true)->Pax . '',
                    'code' => $tax->attributes('', true)->Code . '',
                    'currency' => $tax->attributes('', true)->Cur . '',
                    'amount' => $tax->attributes('', true)->Amnt . '',
                    'description' => $tax->attributes('', true)->desc . '',
                    'separate' => $tax->attributes('', true)->separate . '',
                ];
            }

            $payments = [];
            foreach ($xmlObject->PNR->Payments as $payment) {
                $payments[] = [
                    'line' => $payment->FOP->attributes('', true)->Line . '',
                    'form_of_payment_id' => $payment->FOP->attributes('', true)->FOPID . '',
                    'currency' => $payment->FOP->attributes('', true)->PayCur . '',
                    'amount' => $payment->FOP->attributes('', true)->PayAmt . '',
                    'reference' => $payment->FOP->attributes('', true)->PayRef . '',
                    'pnr_currency' => $payment->FOP->attributes('', true)->PNRCur . '',
                    'pnr_amount' => $payment->FOP->attributes('', true)->PNRAmt . '',
                    'pnr_exchange_rate' => $payment->FOP->attributes('', true)->PNRExRate . '',
                    'payment_date' => $payment->FOP->attributes('', true)->PayDate . '',
                ];
            }

            $tickets = [];
            foreach ($xmlObject->PNR->Tickets as $ticket) {
                $tickets[] = [
                    'pax_id' => $ticket->TKT->attributes('', true)->Pax . '',
                    'ticket_id' => $ticket->TKT->attributes('', true)->TKTID . '',
                    'ticket_number' => $ticket->TKT->attributes('', true)->TktNo . '',
                    'flight_date' => $ticket->TKT->attributes('', true)->TktFltDate . '',
                    'flight_number' => $ticket->TKT->attributes('', true)->TktFltNo . '',
                    'departure' => $ticket->TKT->attributes('', true)->TktDepart . '',
                    'arrival' => $ticket->TKT->attributes('', true)->TktArrive . '',
                    'class' => $ticket->TKT->attributes('', true)->TktBClass . '',
                    'issue_date' => $ticket->TKT->attributes('', true)->IssueDate . '',
                    'status' => $ticket->TKT->attributes('', true)->Status . '',
                    'segment_number' => $ticket->TKT->attributes('', true)->SegNo . '',
                    'title' => $ticket->TKT->attributes('', true)->Title . '',
                    'first_name' => $ticket->TKT->attributes('', true)->Firstname . '',
                    'last_name' => $ticket->TKT->attributes('', true)->Surname . '',
                    'ticket_for' => $ticket->TKT->attributes('', true)->TktFor . '',
                    'sequence_number' => $ticket->TKT->attributes('', true)->SequenceNo . '',
                    'lounge_access' => $ticket->TKT->attributes('', true)->LoungeAccess . '',
                    'fast_track' => $ticket->TKT->attributes('', true)->FastTrack . '',
                    'hold_pcs' => $ticket->TKT->attributes('', true)->HoldPcs . '',
                    'hold_weight' => $ticket->TKT->attributes('', true)->HoldWt . '',
                    'hand_weight' => $ticket->TKT->attributes('', true)->HandWt . '',
                    'web_checkout' => $ticket->TKT->attributes('', true)->WebCheckOut . '',
                ];
            }

            $pnr = [
                'type' => 'confirm',
                'rloc' => $xmlObject->PNR->attributes('', true)->RLOC . "",
                'is_pnr_locked' => $xmlObject->PNR->attributes('', true)->PNRLocked . "",
                'is_pnr_edittable' => $xmlObject->PNR->attributes('', true)->editPNR . "",
                'is_voidable' => $xmlObject->PNR->attributes('', true)->CanVoid . "",

                'total_price' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Total,
                'currency' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Cur,
                'fare_qoute' => [
                    'currency' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Cur,
                    'fqi' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->FQI,
                    'price' => [
                        'total' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Total,
                        'fare' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Fare,
                        'tax1' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax1,
                        'tax2' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax2,
                        'tax3' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax3,
                    ],
                    'taxes' => $pax_taxes,
                ],
                'fare_store' => [
                    'miles' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->miles,
                    'hold_pices' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldPcs,
                    'hold_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldWt,
                    'hand_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HandWt,
                ],
                'payments' => $payments,
                'tickets' => $tickets,
                'itinerary' => [
                    'line' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Line . "",
                    'airline_id' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->AirID . "",
                    'flight_number' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->FltNo . "",
                    'class' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Class . "",
                    'departure' => [
                        'iataCode' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Depart . "",
                        'terminal' => '',
                        'at' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->DepDate . " " . $xmlObject->PNR->Itinerary->Itin->attributes('', true)->DepTime,
                    ],
                    'arrival' => [
                        'iataCode' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Arrive . "",
                        'terminal' => '',
                        'at' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ArrDate . " " . $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ArrTime,
                    ],
                    'status' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Status . "",
                    'number_of_passengers' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->PaxQty . "",
                    // 'ArrOfst' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ArrOfst,
                    'number_of_stops' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Stops . "",
                    'cabine' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Cabin . "",
                    'class_band' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ClassBand . "",
                    'class_band_display_name' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ClassBandDisplayName . "",
                    'online_checkin' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->onlineCheckin . "",
                    'select_seat' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->SelectSeat . "",
                    'mmb_select_seat' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->MMBSelectSeat . "",
                    'is_online_checkin_allowed' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->MMBCheckinAllowed . "",
                ],
            ];
        }

        return $pnr;
    }

    public function createPnr($data)
    {

        $issue_pnr_command = "";

        $date_of_birth_list = [];

        foreach ($data["passengers"] as $passenger) {
            $date_of_birth = $passenger['date_of_birth']['year'] . '-' . $passenger['date_of_birth']['month'] . '-' . $passenger['date_of_birth']['day'];
            $date_of_birth_list[$passenger['index']] = $date_of_birth;
            switch ($passenger["type"]) {
                case "adult":
                    $issue_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . '#/';
                    break;
                case "child":
                    if (is_child($date_of_birth)) {
                        $issue_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . '#.CH' . age_in_years($date_of_birth) . '/';
                    }
                    break;
                case "seated_infant":
                    if (is_infant($date_of_birth)) {
                        $issue_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . '#.IS' . age_in_month($date_of_birth) . '/';
                    }
                    break;
                case "infant":
                    if (is_infant($date_of_birth)) {
                        $issue_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . '#.IN' . age_in_month($date_of_birth) . '/';
                    }
                    break;

            }
        }

        foreach ($data["passengers"] as $passenger) {
            if ($passenger['is_primary_contact']) {
                $issue_pnr_command .= "^9-" . $passenger['index'] . "M*" . $data['contact']['phone'];
            }
        }

        $issue_pnr_command = substr($issue_pnr_command, 0, -1);

        $count_passengers = count($data["passengers"]);

        foreach ($data['offers'] as $offer) {
            $issue_pnr_command .= "^0" . $offer['carrierCode'] . $offer['number'] . $offer['class'] . date('dM', strtotime($offer['departure']['at'])) . $offer['departure']['iataCode'] . $offer['arrival']['iataCode'] . "NN" . $count_passengers;
        }

        $issue_pnr_command = "I^" . $issue_pnr_command;


        $issue_pnr_command .= "^FG";
        $issue_pnr_command .= "^FS1";
        $issue_pnr_command .= "^*R";
        // $issue_pnr_command .= "^MI-ABC TOURS01012";
        $issue_pnr_command .= "^MI";
        $issue_pnr_command .= "^EZT*R";
        $issue_pnr_command .= "^EZRE";
        $issue_pnr_command .= "^*R~x";

        // return $issue_pnr_command;

        $response = $this->runCommand($issue_pnr_command);

        $result = $response?->response;

        $pnr = [];

        if ($result != null) {
            $xml = "";

            $xml = "<xml>" . $result . "</xml>";

            $xmlObject = simplexml_load_string($xml);

            return parse_pnr($xmlObject);

            $pax_taxes = [];
            foreach ($xmlObject->PNR->FareQuote->FareTax->PaxTax as $tax) {
                $pax_taxes[] = [
                    'segment_id' => $tax->attributes('', true)->Seg . '',
                    'pax_id' => $tax->attributes('', true)->Pax . '',
                    'code' => $tax->attributes('', true)->Code . '',
                    'currency' => $tax->attributes('', true)->Cur . '',
                    'amount' => $tax->attributes('', true)->Amnt . '',
                    'description' => $tax->attributes('', true)->desc . '',
                    'separate' => $tax->attributes('', true)->separate . '',
                ];
            }

            $payments = [];
            foreach ($xmlObject->PNR->Payments as $payment) {
                $payments[] = [
                    'line' => $payment->FOP->attributes('', true)->Line . '',
                    'form_of_payment_id' => $payment->FOP->attributes('', true)->FOPID . '',
                    'currency' => $payment->FOP->attributes('', true)->PayCur . '',
                    'amount' => $payment->FOP->attributes('', true)->PayAmt . '',
                    'reference' => $payment->FOP->attributes('', true)->PayRef . '',
                    'pnr_currency' => $payment->FOP->attributes('', true)->PNRCur . '',
                    'pnr_amount' => $payment->FOP->attributes('', true)->PNRAmt . '',
                    'pnr_exchange_rate' => $payment->FOP->attributes('', true)->PNRExRate . '',
                    'payment_date' => $payment->FOP->attributes('', true)->PayDate . '',
                ];
            }

            $tickets = [];
            foreach ($xmlObject->PNR->Tickets as $ticket) {
                $tickets[] = [
                    'pax_id' => $ticket->TKT->attributes('', true)->Pax . '',
                    'ticket_id' => $ticket->TKT->attributes('', true)->TKTID . '',
                    'ticket_number' => $ticket->TKT->attributes('', true)->TktNo . '',
                    'flight_date' => $ticket->TKT->attributes('', true)->TktFltDate . '',
                    'flight_number' => $ticket->TKT->attributes('', true)->TktFltNo . '',
                    'departure' => $ticket->TKT->attributes('', true)->TktDepart . '',
                    'arrival' => $ticket->TKT->attributes('', true)->TktArrive . '',
                    'class' => $ticket->TKT->attributes('', true)->TktBClass . '',
                    'issue_date' => $ticket->TKT->attributes('', true)->IssueDate . '',
                    'status' => $ticket->TKT->attributes('', true)->Status . '',
                    'segment_number' => $ticket->TKT->attributes('', true)->SegNo . '',
                    'title' => $ticket->TKT->attributes('', true)->Title . '',
                    'first_name' => $ticket->TKT->attributes('', true)->Firstname . '',
                    'last_name' => $ticket->TKT->attributes('', true)->Surname . '',
                    'ticket_for' => $ticket->TKT->attributes('', true)->TktFor . '',
                    'sequence_number' => $ticket->TKT->attributes('', true)->SequenceNo . '',
                    'lounge_access' => $ticket->TKT->attributes('', true)->LoungeAccess . '',
                    'fast_track' => $ticket->TKT->attributes('', true)->FastTrack . '',
                    'hold_pcs' => $ticket->TKT->attributes('', true)->HoldPcs . '',
                    'hold_weight' => $ticket->TKT->attributes('', true)->HoldWt . '',
                    'hand_weight' => $ticket->TKT->attributes('', true)->HandWt . '',
                    'web_checkout' => $ticket->TKT->attributes('', true)->WebCheckOut . '',
                ];
            }

            $pnr = [
                'type' => 'pnr',
                'rloc' => $xmlObject->PNR->attributes('', true)->RLOC . "",
                'is_pnr_locked' => $xmlObject->PNR->attributes('', true)->PNRLocked . "",
                'is_pnr_edittable' => $xmlObject->PNR->attributes('', true)->editPNR . "",
                'is_voidable' => $xmlObject->PNR->attributes('', true)->CanVoid . "",

                'total_price' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Total,
                'currency' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Cur,
                'fare_qoute' => [
                    'currency' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Cur,
                    'fqi' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->FQI,
                    'price' => [
                        'total' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Total,
                        'fare' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Fare,
                        'tax1' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax1,
                        'tax2' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax2,
                        'tax3' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax3,
                    ],
                    'taxes' => $pax_taxes,
                ],
                'fare_store' => [
                    'miles' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->miles,
                    'hold_pices' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldPcs,
                    'hold_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldWt,
                    'hand_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HandWt,
                ],
                'payments' => $payments,
                'tickets' => $tickets,
                'itinerary' => [
                    'line' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Line . "",
                    'airline_id' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->AirID . "",
                    'flight_number' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->FltNo . "",
                    'class' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Class . "",
                    'departure' => [
                        'iataCode' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Depart . "",
                        'terminal' => '',
                        'at' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->DepDate . " " . $xmlObject->PNR->Itinerary->Itin->attributes('', true)->DepTime,
                    ],
                    'arrival' => [
                        'iataCode' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Arrive . "",
                        'terminal' => '',
                        'at' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ArrDate . " " . $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ArrTime,
                    ],
                    'status' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Status . "",
                    'number_of_passengers' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->PaxQty . "",
                    // 'ArrOfst' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ArrOfst,
                    'number_of_stops' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Stops . "",
                    'cabine' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Cabin . "",
                    'class_band' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ClassBand . "",
                    'class_band_display_name' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ClassBandDisplayName . "",
                    'online_checkin' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->onlineCheckin . "",
                    'select_seat' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->SelectSeat . "",
                    'mmb_select_seat' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->MMBSelectSeat . "",
                    'is_online_checkin_allowed' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->MMBCheckinAllowed . "",
                ],
            ];
        }

        return $pnr;
    }

    # Helpers
    public function runCommand($cmd, $log = true)
    {
        if ($this->mode == "user_auth") {
            $auth_user = $this->aeroToken->data['auth_user'];
            $auth_pass = $this->aeroToken->data['auth_pass'];

            if (cache()->has($this->aeroToken->iata)) {

                $session = cache()->get($this->aeroToken->iata);
                // dd($session);
                $rq = Http::post($this->url . '/VARS/Agent/res/EmulatorWS.asmx/SendCommand?' . $session, [
                    'VRSCommand' => $cmd,
                ]);

                if (str_contains($rq->json('d')['Data'], 'VARS.SystemLibrary.NotSinedInException')) {
                    $rq = Http::withBody('{"loginRq":{"UserName":"' . $auth_user . '","Password":"' . $auth_pass . '"}}', 'application/json')
                        ->post($this->url . '/VARS/Agent/WebServices/LoginWs.asmx/DoLogin?VarsSessionID=undefined');

                    $body = json_decode($rq->body());
                    $next_url = $body->d->NextURL;

                    $exploded_url = explode('&', $next_url);
                    $session = $exploded_url[count($exploded_url) - 1];

                    cache()->put($this->aeroToken->iata, $session);

                    $rq = Http::post($this->url . '/VARS/Agent/res/EmulatorWS.asmx/SendCommand?' . $session, [
                        'VRSCommand' => $cmd,
                    ]);
                }

                $user_id = (request()->user() != null) ? request()?->user()?->id : 1;
                if ($log) {
                    $store_job = new StoreCommandRequestJob($this->aeroToken, $cmd, $rq->json('d')['Data'] ?? '<EMPTY></EMPTY>', $user_id);
                    dispatch($store_job)->onQueue('default');
                } else {
                }

                // \App\Models\CommandRequest::create([
                //     'aero_token_id' => $this->aeroToken->id,
                //     'user_id' => (request()->user() != null) ? request()?->user()?->id : 1,
                //     'command' => $cmd,
                //     'result' => $rq->json('d')['Data'] ?? '<EMPTY></EMPTY>',
                // ]);

                return (object) [
                    'response' => $rq->json('d')['Data']
                ];
                // return response($rq->json('d')['Data'], 200, [
                //     'Content-Type' => 'application/xml'
                // ]);
            } else {

                $rq = Http::withBody('{"loginRq":{"UserName":"' . $auth_user . '","Password":"' . $auth_pass . '"}}', 'application/json')
                    ->post($this->url . '/VARS/Agent/WebServices/LoginWs.asmx/DoLogin?VarsSessionID=undefined');


                $body = json_decode($rq->body());
                $next_url = $body->d->NextURL;

                $exploded_url = explode('&', $next_url);
                $session = $exploded_url[count($exploded_url) - 1];

                cache()->put($this->aeroToken->iata, $session);

                $rq = Http::post($this->url . '/VARS/Agent/res/EmulatorWS.asmx/SendCommand?' . $session, [
                    'VRSCommand' => $cmd,
                ]);

                // \App\Models\CommandRequest::create([
                //     'aero_token_id' => $this->aeroToken->id,
                //     'user_id' => (request()->user() != null) ? request()?->user()?->id : 1,
                //     'command' => $cmd,
                //     'result' => $rq->json('d')['Data'] ?? '<EMPTY></EMPTY>',
                // ]);

                if ($log) {
                    $user_id = (request()->user() != null) ? request()?->user()?->id : 1;
                    $store_job = new StoreCommandRequestJob($this->aeroToken, $cmd, $rq->json('d')['Data'], $user_id);
                    dispatch($store_job)->onQueue('default');
                } else {
                }

                return (object) [
                    'response' => $rq->json('d')['Data']
                ];
            }
        }

        if ($this->mode == 'api') {
            return $this->runCommandV4($cmd, $log);
        }
        // ini_set('default_socket_timeout', 600);

        // dd($this->aeroToken->data['api_token']);
        // $method = 'RunVRSCommand';
        // $params = [
        //     // 'RunVRSCommandRequest' => [
        //     'Token' => $this->aeroToken->data['api_token'],
        //     'Command' => $cmd,
        //     // ],  
        // ];

        // // SSL Configuration (SSL: Off)
        // $context = stream_context_create([
        //     'ssl' => [
        //         'verify_peer' => true,
        //         'verify_peer_name' => true,
        //         'allow_self_signed' => true
        //     ]
        // ]);

        // $this->client = Soap::to($this->url)
        //     ->withOptions([
        //         // Complement (Optional)
        //         'allow_redirects' => RedirectMiddleware::$defaultSettings,
        //         'http_errors' => true,
        //         'decode_content' => false,
        //         'verify' => true,
        //         'cookies' => true,
        //         'idn_conversion' => false,
        //         // SSL Configuration
        //         'stream_context' => $context,
        //         'soap_version' => SOAP_1_2,
        //         'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
        //         'encoding' => 'utf-8',
        //         'keep_alive' => true,
        //     ])
        //     ->call($method, $params);

        // \App\Models\CommandRequest::create([
        //     'aero_token_id' => $this->aeroToken->id,
        //     'user_id' => (request()->user() != null) ? request()?->user()?->id : 1,
        //     'command' => $cmd,
        //     'result' => $this->client?->response ?? '<EMPTY></EMPTY>',
        // ]);

        // return $this->client;
    }

    public function runCommandV2($cmd)
    {
        $factory = new Factory();
        $client = $factory->create(
            new Client(),
            new StreamFactory(),
            new RequestFactory(),
            $this->url
        );

        $result = $client->call('RunVRSCommand', [
            [
                'Token' => $this->aeroToken->data['api_token'],
                'Command' => $cmd,
            ]
        ]);

        return (object) [
            'response' => $result,
        ];
    }

    static $wsdls = [];
    public function runCommandV3($cmd)
    {
        $client = null;
        if (isset(self::$wsdls[$this->aeroToken->iata])) {
            $client = self::$wsdls[$this->aeroToken->iata];
        } else {
            $cache = new \nusoap_wsdlcache(storage_path(), 0);
            $wsdl = $cache->get($this->url);

            if (is_null($wsdl)) {
                $wsdl = new \wsdl($this->url);
                $cache->put($wsdl);
            }

            $client = new \nusoap_client($wsdl, true);

            self::$wsdls[$this->aeroToken->iata] = $client;
        }


        // Config

        // $client->soap_defencoding = 'UTF-8';
        // $client->decode_utf8 = FALSE;

        // Calls
        $result = $client->call('RunVRSCommand', [
            [
                'Token' => $this->aeroToken->data['api_token'],
                'Command' => $cmd,
            ]
        ]);

        // $factory = new Factory();
        // $client = $factory->create(
        //     new Client(),
        //     new StreamFactory(),
        //     new RequestFactory(),
        //     $this->url
        // );

        // $result = $client->call('RunVRSCommand', [
        //     [
        //         'Token' => $this->aeroToken->data['api_token'],
        //         'Command' => $cmd,
        //     ]
        // ]);

        return (object) [
            'response' => $result,
        ];
    }

    public function runCommandV4($cmd, $log = true)
    {
        $xmlBody = '<?xml version="1.0" encoding="UTF-8"?>
        <s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns="http://videcom.com/">
            <s:Body>
                <ns:msg>
                    <ns:Token>' . $this->aeroToken->data['api_token'] . '</ns:Token>
                    <ns:Command>' . $cmd . '</ns:Command>
                </ns:msg>
            </s:Body>
        </s:Envelope>';
        $http = Http::timeout(60)
            ->withHeaders([
                'Content-Type' => 'application/soap+xml',
                'SOAPAction' => 'http://videcom.com/RunVRSCommand'
            ])
            ->withBody($xmlBody, "text/xml")
            ->post($this->aeroToken->data['url']);
        // return $http->body();
        // $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $http->body());
        // return $response;
        $x = $http->getBody();
        $x = str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $x);
        // $x = str_replace('<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">', '', $x);
        $x = str_replace('<soap:Body>', '', $x);
        $x = str_replace('<RunVRSCommandResult xmlns="http://videcom.com/">', '', $x);
        $x = str_replace('</RunVRSCommandResult>', '', $x);
        $x = str_replace('</soap:Body>', '', $x);
        // return $x;

        $xml = simplexml_load_string($x);


        $body = $xml->xpath('/soap:Envelope')[0];

        if ($log) {
            \App\Models\CommandRequest::create([
                'aero_token_id' => $this->aeroToken->id,
                'user_id' => (request()?->user() != null) ? request()?->user()?->id : 1,
                'command' => $cmd,
                'result' => $body ?? '<EMPTY></EMPTY>',
            ]);
        }
        return (object) [
            'response' => $body,
        ];
    }

    public function runCommandV5($cmd)
    {
        $wsdl = $this->aeroToken->data['url'];

        $options = [
            'cache_wsdl' => WSDL_CACHE_NONE,
            'encoding' => 'UTF-8',
            'soap_version' => SOAP_1_2,
            'trace' => 1,
            'exceptions' => 1,
            'connection_timeout' => 180,
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => "Authorization: Basic " . base64_encode("username:password")
                ]
            ])
        ];

        try {
            $client = new \SoapClient($wsdl, $options);
            $params = [
                'Token' => $this->aeroToken->data['api_token'],
                'Command' => $cmd,
            ];

            $result = $client->__soapCall('RunVRSCommand', [$params]);
            return (object) [
                'response' => $result,
            ];
        } catch (Exception $e) {
            echo 'Exception: ' . $e->getMessage();
        }
    }

    public function getAsyncCommandRunner($cmd, $log = true)
    {
        if ($this->mode == "user_auth") {
            $auth_user = $this->aeroToken->data['auth_user'];
            $auth_pass = $this->aeroToken->data['auth_pass'];

            if (cache()->has($this->aeroToken->iata)) {

                $session = cache()->get($this->aeroToken->iata);
                // dd($session);
                // $rq = Http::post($this->url . '/VARS/Agent/res/EmulatorWS.asmx/SendCommand?' . $session, [
                //     'VRSCommand' => $cmd,
                // ]);
                $body = json_encode([
                    'VRSCommand' => $cmd,
                ]);
                $request = new \GuzzleHttp\Psr7\Request("POST", $this->url . '/VARS/Agent/res/EmulatorWS.asmx/SendCommand?' . $session, [
                    'Content-Type' => 'application/json'
                ], $body);

                return $request;

                if (str_contains($rq->json('d')['Data'], 'VARS.SystemLibrary.NotSinedInException')) {
                    $rq = Http::withBody('{"loginRq":{"UserName":"' . $auth_user . '","Password":"' . $auth_pass . '"}}', 'application/json')
                        ->post($this->url . '/VARS/Agent/WebServices/LoginWs.asmx/DoLogin?VarsSessionID=undefined');

                    $body = json_decode($rq->body());
                    $next_url = $body->d->NextURL;

                    $exploded_url = explode('&', $next_url);
                    $session = $exploded_url[count($exploded_url) - 1];

                    cache()->put($this->aeroToken->iata, $session);

                    $body = json_encode([
                        'VRSCommand' => $cmd,
                    ]);
                    $request = new \GuzzleHttp\Psr7\Request("POST", $this->url . '/VARS/Agent/res/EmulatorWS.asmx/SendCommand?' . $session, [
                        'Content-Type' => 'application/json'
                    ], $body);

                    return $request;

                    // return Http::async()->post($this->url . '/VARS/Agent/res/EmulatorWS.asmx/SendCommand?' . $session, [
                    //     'VRSCommand' => $cmd,
                    // ]);
                }

            } else {

                $rq = Http::withBody('{"loginRq":{"UserName":"' . $auth_user . '","Password":"' . $auth_pass . '"}}', 'application/json')
                    ->post($this->url . '/VARS/Agent/WebServices/LoginWs.asmx/DoLogin?VarsSessionID=undefined');


                if ($rq->successful()) {
                    $body = json_decode($rq->body());
                    $next_url = $body->d->NextURL;
    
                    $exploded_url = explode('&', $next_url);
                    $session = $exploded_url[count($exploded_url) - 1];
    
                    cache()->put($this->aeroToken->iata, $session);
    
                    $body = json_encode([
                        'VRSCommand' => $cmd,
                    ]);
                    $request = new \GuzzleHttp\Psr7\Request("POST", $this->url . '/VARS/Agent/res/EmulatorWS.asmx/SendCommand?' . $session, [
                        'Content-Type' => 'application/json'
                    ], $body);
    
                    return $request;
                }
                
                return null;
            }
        }

        if ($this->mode == "api") {
            $xmlBody = '<?xml version="1.0" encoding="UTF-8"?>
            <s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns="http://videcom.com/">
                <s:Body>
                    <ns:msg>
                        <ns:Token>' . $this->aeroToken->data['api_token'] . '</ns:Token>
                        <ns:Command>' . $cmd . '</ns:Command>
                    </ns:msg>
                </s:Body>
            </s:Envelope>';

            $url = str_replace('?WSDL', '', $this->aeroToken->data['url']);

            $request = new \GuzzleHttp\Psr7\Request("POST", $url, [
                'Content-Type' => 'text/xml',
                'Accept' => 'application/xml',
                'SOAPAction' => 'http://videcom.com/RunVRSCommand'
            ], $xmlBody);

            return $request;
            // return Http::async()->timeout(60)
            //     ->withHeaders([
            //         'Content-Type' => 'application/soap+xml',
            //         'SOAPAction' => 'http://videcom.com/RunVRSCommand'
            //     ])
            //     ->withBody($xmlBody, "text/xml")
            //     ->post($this->aeroToken->data['url']);

        }
    }

    private static function sortOffers($flights)
    {
        $result = [
            'economy' => [
                'lowest_price' => null,
                'offers' => [],
            ],
            'business' => [
                'lowest_price' => null,
                'offers' => [],
            ],
            'first' => [
                'lowest_price' => null,
                'offers' => [],
            ],
        ];

        foreach ($flights as $flight) {
            $class = config('airline.class_fare_type.' . $flight['id']);
            $flight_price = floatval($flight['price']);

            // if ($flight_price < $result[$class]['lowest_price'] || $result[$class]['lowest_price'] == null) {
            $result[$class]['lowest_price'] = $flight_price;
            // }

            array_push($result[$class]['offers'], $flight);

        }



        return $result;
    }

    private function prepareFareWithPrice($offer)
    {
        $adults = 0; //$offer['_meta']['passengers']['adults'];
        $children = 0; //$offer['_meta']['passengers']['children'];
        $infants = 0; //$offer['_meta']['passengers']['infants'];

        // dd($offer);
        // $flight_number = $offer['itinerary']['operator']->iata . $offer['itinerary']['flight_number'];
        $flight_number = $offer['flight_number'];
        $departure_date = date('dM', strtotime($offer['departure']['datetime']));
        $departure_airport = $offer['departure']['airport']['iata'];// $offer['itinerary']['departure']['airport']->IATA;
        $arrival_airport = $offer['arrival']['airport']['iata'];// $offer['itinerary']['arrival']['airport']->IATA;

        $passengers_command_segment = "";
        $letter = "A";

        foreach ($offer['travelers'] as $traveler) {
            switch ($traveler['type']) {
                case 'ADULT':
                    $passengers_command_segment .= $letter . '#/';
                    $adults++;
                    break;
                case 'CHILD':
                    $passengers_command_segment .= $letter . '#.CH10/';
                    $children++;
                    break;
                case 'INFANT':
                    $passengers_command_segment .= $letter . '#.IN06/';
                    $infants++;
                    break;
            }

            // 
            $letter++;
        }

        $avilable_classes = collect($offer["_meta"]['availabilities'])->where('departure_date', date('Y-m-d', strtotime($departure_date)))->first();

        // return $avilable_classes;

        for ($i = 0; $i < count($avilable_classes['seats']); $i++) {
            if ($avilable_classes['seats'][$i]['count'] > 0) {
                $command = "I^-" . ($adults + $children + $infants) . "Pax/" . $passengers_command_segment . "^0" . $flight_number . $avilable_classes['seats'][$i]['class']
                    . $departure_date . $departure_airport . $arrival_airport . "QQ" . ($adults + $children) . "^FG^FS1^*r~x ";

                $response = $this->runCommand($command);

                $result = $response?->response;
                if ($result == 'ERROR - no fare available') {
                    // Nothing
                } else if ($result != null) {
                    $xml = $result;

                    $xmlObject = simplexml_load_string($xml);

                    $jsonFormatData = json_encode($xmlObject);

                    // return $jsonFormatData;
                    $result = json_decode($jsonFormatData, true);

                    // return $result;
                    $class_offer = [
                        'cabine' => $avilable_classes['seats'][$i]['cabine'],
                        'seats' => $avilable_classes['seats'][$i]['count'],
                        'class' => $avilable_classes['seats'][$i]['class'],
                        'class_band_display_name' => $result['Itinerary']['Itin']['@attributes']['ClassBandDisplayName'],
                        'total_price' => $result['FareQuote']['FQItin']['@attributes']['Total'],
                        'currency' => $result['FareQuote']['FQItin']['@attributes']['Cur'],
                        'fare_qoute' => [
                            'currency' => $result['FareQuote']['FQItin']['@attributes']['Cur'],
                            'fqi' => $result['FareQuote']['FQItin']['@attributes']['FQI'],
                            'price' => [
                                'total' => $result['FareQuote']['FQItin']['@attributes']['Total'],
                                'fare' => $result['FareQuote']['FQItin']['@attributes']['Fare'],
                                'tax1' => $result['FareQuote']['FQItin']['@attributes']['Tax1'],
                                'tax2' => $result['FareQuote']['FQItin']['@attributes']['Tax2'],
                                'tax3' => $result['FareQuote']['FQItin']['@attributes']['Tax3'],
                            ],
                            'taxes' => [

                            ]
                        ],
                        'fare_store' => [
                            'miles' => $result['FareQuote']['FareStore'][0]['SegmentFS']['@attributes']['miles'],
                            'hold_pices' => $result['FareQuote']['FareStore'][0]['SegmentFS']['@attributes']['HoldPcs'],
                            'hold_wieght' => $result['FareQuote']['FareStore'][0]['SegmentFS']['@attributes']['HoldWt'],
                            'hand_wieght' => $result['FareQuote']['FareStore'][0]['SegmentFS']['@attributes']['HandWt'],
                        ]
                    ];

                    foreach ($result['FareQuote']['FareTax']['PaxTax'] as $pax_tax) {
                        $class_offer['fare_qoute']['taxes'][] = [
                            'traveler_id' => $pax_tax['@attributes']['Pax'],
                            'code' => $pax_tax['@attributes']['Code'],
                            'currency' => $pax_tax['@attributes']['Cur'],
                            'amount' => $pax_tax['@attributes']['Amnt'],
                            'description' => $pax_tax['@attributes']['desc'],
                            'separate' => $pax_tax['@attributes']['separate'],
                        ];
                    }

                    $offer['offers'][] = $class_offer;
                }
            }
        }

        $offer['cabines'] = [
            'economy' => [],
            'business' => [],
            'first' => [],
        ];

        foreach ($offer['offers'] as $_offer) {
            if ($_offer['cabine'] == "Y") {
                $_offer['cabine_type'] = "economy";
                $offer['cabines']["economy"][] = $_offer;
            } else if ($_offer['cabine'] == "C") {
                $_offer['cabine_type'] = "business";
                $offer['cabines']["business"][] = $_offer;
            } else {
                $_offer['cabine_type'] = "other";
                $offer['cabines'][$_offer['cabine']][] = $_offer;
            }
        }

        return $offer;
    }

    public function seatMap($flight_number, $date, $origin, $destination, $cabine = "standard")
    {
        // $command = "LS" . $flight_number . "/" . date('dM', strtotime($date)) . $origin . $destination . "[CB=" . $cabine . "]~x";
        $command = "LS" . $flight_number . "/" . date('dM', strtotime($date)) . $origin . $destination . "~x";

        // $response = cache()->remember('seat-map|' . $command, now()->addMinutes(10), function () use ($command) {
        //     return $this->runCommand($command)?->response;
        // });

        $response = $this->runCommand($command);

        $result = $response?->response;
        if ($result == 'ERROR - no fare available') {
            // Nothing
        } else if ($result != null) {
            // return $result;
            $xml = $result;

            if (preg_match('/<xml>(.*?)<\/xml>/', $result, $match) == 1) {
                $xml = $match[1];
            }

            $xml = "<xml>" . $xml . "</xml>";

            $xmlObject = simplexml_load_string($xml);

            $seatmap = [
                [],
            ];

            $info = [
                'flight_number' => (string) $xmlObject->Seats->SeatsFlt->attributes('', true)->FltNo,
                'origin' => (string) $xmlObject->Seats->SeatsFlt->attributes('', true)->Depart,
                'destination' => (string) $xmlObject->Seats->SeatsFlt->attributes('', true)->Destin,
                'aircraft' => getAircraft($xmlObject->Seats->SeatsFlt->attributes('', true)->Ref),
                'display_info' => (string) $xmlObject->Seats->SeatsFlt->attributes('', true)->DisplayInfo,
            ];

            foreach ($xmlObject->Seats->Seat as $seat) {
                $type = "aisle";

                switch ($seat->attributes('', true)->CellDescription) {
                    case 'A':
                        $type = "code";
                    case 'B':
                        $type = "code";
                    case 'C':
                        $type = "code";
                    case 'D':
                        $type = "code";
                    case 'E':
                        $type = "code";
                    case 'F':
                        $type = "code";
                    case 'G':
                        $type = "code";
                    case 'H':
                        $type = "code";
                    case 'I':
                        $type = "code";
                    case 'J':
                        $type = "code";
                    case 'K':
                        $type = "code";
                    case 'L':
                        $type = "code";
                    case 'M':
                        $type = "code";
                    case 'N':
                        $type = "code";
                        break;
                    case 'Seat':
                        $type = "seat";
                        break;
                    case 'EmergencySeat':
                        $type = "emergency_seat";
                        break;
                    case 'SeatPlanWidthMarker':
                        $type = "seat_plan";
                        break;
                    case 'Aisle':
                        $type = "aisle";
                        break;
                    default:
                        $type = (string) $seat->attributes('', true)->CellDescription;

                }

                $seatmap[$seat->attributes('', true)->Row - 1][$seat->attributes('', true)->Col - 1] = [
                    'id' => (string) $seat->attributes('', true)->SeatID,
                    'code' => (string) $seat->attributes('', true)->Code,
                    'cabin' => (string) $seat->attributes('', true)->CabinClass,
                    'description' => (string) $seat->attributes('', true)->CellDescription,
                    'no_infant_seat' => (string) $seat->attributes('', true)->NoInfantSeat,
                    'prm_seat' => (string) $seat->attributes('', true)->PRMSeat,
                    'price' => (double) $seat->attributes('', true)->scprice,
                    'currency' => (string) $seat->attributes('', true)->cur,
                    'seat_charge_code' => (string) $seat->attributes('', true)->sccode,
                    'seat_charge_info' => (string) $seat->attributes('', true)->scinfo,

                    'row' => (int) $seat->attributes('', true)->Row,
                    'col' => (int) $seat->attributes('', true)->Col,
                    'type' => $type,
                    'is_available' => ((int) $seat->attributes('', true)->SeatID == 0),
                ];
            }

            $seats = [];
            foreach ($seatmap as $row) {
                ksort($row);
                $seats[] = $row;
            }

            return [
                'info' => $info,
                'seats' => $seats,
            ];
        }
    }

    private function priceSegment($segment, $adults, $children, $infants)
    {
        $flight_number = $segment['carrierCode'] . $segment['number'];
        $departure_date = date('dM', strtotime($segment['departure']['at']));
        $departure_airport = $segment['departure']['iataCode'];
        $arrival_airport = $segment['arrival']['iataCode'];

        $key = "price_segment_" . $flight_number . "_" . $departure_date . "_" . $departure_airport . "_" . $arrival_airport
            . "_AD-" . $adults . "_CH-" . $children . "_IN-" . $infants;


        $result = cache()->remember($key, env('CACHE_REMEBER_TTL', 1), function () use ($segment, $adults, $children, $infants) {

            $flight_number = $segment['carrierCode'] . $segment['number'];
            $departure_date = date('dM', strtotime($segment['departure']['at']));
            $departure_airport = $segment['departure']['iataCode'];
            $arrival_airport = $segment['arrival']['iataCode'];

            $passengers_command_segment = "";
            $letter = "A";

            for ($i = 0; $i < $adults; $i++) {
                $passengers_command_segment .= $letter . '#/';
                $letter++;
            }
            for ($i = 0; $i < $children; $i++) {
                $passengers_command_segment .= $letter . '#.CH10/';
                $letter++;
            }
            for ($i = 0; $i < $infants; $i++) {
                $passengers_command_segment .= $letter . '#.IN06/';
                $letter++;
            }

            $avialablities = collect($this->flightAvialability([
                'from' => $segment['departure']['iataCode'],
                'to' => $segment['arrival']['iataCode'],
                'date' => $segment['departure']['at'],
            ]));

            $avialablity = $avialablities->where('departure_date', date("Y-m-d", strtotime($segment['departure']['at'])))->first();
            // return $avialablity;

            $offer = [];

            foreach ($avialablity['seats'] as $seat) {
                if ($seat['count'] > 0) {
                    $command = "I^-" . ($adults + $children + $infants) . "Pax/" . $passengers_command_segment . "^0" . $flight_number . $seat['class']
                        . $departure_date . $departure_airport . $arrival_airport . "QQ" . ($adults + $children) . "^FG^FS1^*r~x ";

                    // return $command;
                    $response = $this->runCommand($command);

                    $result = $response?->response;
                    if ($result == 'ERROR - no fare available') {
                        // Nothing
                    } else if ($result != null) {
                        // return $result;
                        $xml = $result;

                        if (preg_match('/<xml>(.*?)<\/xml>/', $result, $match) == 1) {
                            $xml = $match[1];
                        }

                        $xml = "<xml>" . $xml . "</xml>";

                        $xmlObject = simplexml_load_string($xml);

                        // $jsonFormatData = json_encode($xmlObject);

                        // // return $jsonFormatData;
                        // $result = json_decode($jsonFormatData, true);

                        // return $result;
                        $fare_stores = collect([]);
                        foreach ($xmlObject->PNR->FareQuote->FareStore as $fare_store) {
                            $fare_stores->push([
                                'id' => $fare_store->attributes('', true)->FSID . '',
                                'pax' => $fare_store->attributes('', true)->Pax . '',
                                'total' => $fare_store->attributes('', true)->Total . '',
                                'currency' => $fare_store->attributes('', true)->Cur . '',
                            ]);
                        }

                        $class_offer = [
                            'command' => $command,
                            'departure' => $segment['departure'],
                            'arrival' => $segment['arrival'],
                            'carrierCode' => $segment['carrierCode'],
                            'number' => $segment['number'],

                            'cabine' => $seat['cabine'],
                            'seats' => $seat['count'],
                            'class' => $seat['class'],
                            // 'fare_store_obj' => $fare_stores,
                            'class_band_display_name' => "" . $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ClassBandDisplayName,
                            'total_price' => "" . $fare_stores->firstWhere('id', 'Total')['total'],
                            'currency' => "" . $fare_stores->firstWhere('id', 'Total')['currency'],
                            'fare_qoute' => [
                                'currency' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Cur,
                                'fqi' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->FQI,
                                'price' => [
                                    'total' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Total,
                                    'fare' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Fare,
                                    'tax1' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax1,
                                    'tax2' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax2,
                                    'tax3' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax3,
                                ],
                                'taxes' => [

                                ]
                            ],
                            'fare_store' => [
                                'miles' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->miles,
                                'hold_pices' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldPcs,
                                'hold_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldWt,
                                'hand_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HandWt,
                            ]
                        ];

                        // foreach ($result['FareQuote']['FareTax']['PaxTax'] as $pax_tax) {
                        //     $class_offer['fare_qoute']['taxes'][] = [
                        //         'traveler_id' => $pax_tax['@attributes']['Pax'],
                        //         'code' => $pax_tax['@attributes']['Code'],
                        //         'currency' => $pax_tax['@attributes']['Cur'],
                        //         'amount' => $pax_tax['@attributes']['Amnt'],
                        //         'description' => $pax_tax['@attributes']['desc'],
                        //         'separate' => $pax_tax['@attributes']['separate'],
                        //     ];
                        // }

                        $offer[] = $class_offer;
                    }
                }
            }

            return $offer;

        });

        return $result;
    }

    private function priceSegments($segments, $adults, $children, $infants, $combine = true)
    {
        $result = [];

        if ($combine) {
            $segments_command = "";

            $first_segment = null;
            foreach ($segments as $segment) {
                if ($first_segment == null) {
                    $first_segment = $segment;
                }
                $flight_number = $segment['carrierCode'] . $segment['number'];
                $departure_date = date('dM', strtotime($segment['departure']['at']));
                $departure_airport = $segment['departure']['iataCode'];
                $arrival_airport = $segment['arrival']['iataCode'];

                $segments_command .= "^0" . $flight_number . "[SEAT]" . $departure_date . $departure_airport . $arrival_airport . "QQ" . ($adults + $children);
            }




            $key = "price_segment_tow_way_" . $segments_command;


            $result = cache()->remember($key, env('CACHE_REMEBER_TTL', 1), function () use ($first_segment, $segments, $segments_command, $adults, $children, $infants) {

                $passengers_command_segment = "";
                $letter = "A";

                for ($i = 0; $i < $adults; $i++) {
                    $passengers_command_segment .= $letter . '#/';
                    $letter++;
                }
                for ($i = 0; $i < $children; $i++) {
                    $passengers_command_segment .= $letter . '#.CH10/';
                    $letter++;
                }
                for ($i = 0; $i < $infants; $i++) {
                    $passengers_command_segment .= $letter . '#.IN06/';
                    $letter++;
                }

                // $avilable_classes = collect($offer["_meta"]['availabilities'])->where('departure_date', date('Y-m-d', strtotime($departure_date)))->first();

                $avialablities = collect($this->flightAvialability([
                    'from' => $first_segment['departure']['iataCode'],
                    'to' => $first_segment['arrival']['iataCode'],
                    'date' => $first_segment['departure']['at'],
                ]));

                $avialablity = $avialablities->where('departure_date', date("Y-m-d", strtotime($segments[0]['departure']['at'])))->first();
                // return $avialablity;

                $offer = [];

                foreach ($avialablity['seats'] as $seat) {
                    if ($seat['count'] > 0) {
                        $command = "I^-" . ($adults + $children + $infants) . "Pax/" . $passengers_command_segment . str_replace("[SEAT]", $seat['class'], $segments_command) . "^FG^FS1^*r~x ";

                        // return $command;
                        $response = $this->runCommand($command);

                        $result = $response?->response;
                        if ($result == 'ERROR - no fare available') {
                            // Nothing
                        } else if ($result != null) {
                            // return $result;
                            $xml = $result;

                            if (preg_match('/<xml>(.*?)<\/xml>/', $result, $match) == 1) {
                                $xml = $match[1];
                            }

                            $xml = "<xml>" . $xml . "</xml>";

                            $xmlObject = simplexml_load_string($xml);

                            // $jsonFormatData = json_encode($xmlObject);

                            // // return $jsonFormatData;
                            // $result = json_decode($jsonFormatData, true);

                            // return $result;
                            $fare_stores = collect([]);
                            foreach ($xmlObject->PNR->FareQuote->FareStore as $fare_store) {
                                $fare_stores->push([
                                    'id' => $fare_store->attributes('', true)->FSID . '',
                                    'pax' => $fare_store->attributes('', true)->Pax . '',
                                    'total' => $fare_store->attributes('', true)->Total . '',
                                    'currency' => $fare_store->attributes('', true)->Cur . '',
                                ]);
                            }

                            foreach ($xmlObject->PNR->Itinerary->Itin as $segment) {
                                $class_offer = [
                                    'command' => $command,
                                    'departure' => $segment->attributes('', true)->Depart . '',
                                    'arrival' => $segment->attributes('', true)->Arrive . '',
                                    'carrierCode' => $segment->attributes('', true)->AirID . '',
                                    'number' => $segment->attributes('', true)->AirID . $segment->attributes('', true)->FltNo . '',

                                    'cabine' => $seat['cabine'],
                                    'seats' => $seat['count'],
                                    'class' => $seat['class'],
                                    // 'fare_store_obj' => $fare_stores,
                                    'class_band_display_name' => "" . $segment->attributes('', true)->ClassBandDisplayName,
                                    'total_price' => "" . $fare_stores->firstWhere('id', 'Total')['total'],
                                    'currency' => "" . $fare_stores->firstWhere('id', 'Total')['currency'],
                                    'fare_qoute' => [
                                        'currency' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Cur,
                                        'fqi' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->FQI,
                                        'price' => [
                                            'total' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Total,
                                            'fare' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Fare,
                                            'tax1' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax1,
                                            'tax2' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax2,
                                            'tax3' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax3,
                                        ],
                                        'taxes' => [

                                        ]
                                    ],
                                    'fare_store' => [
                                        'miles' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->miles,
                                        'hold_pices' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldPcs,
                                        'hold_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldWt,
                                        'hand_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HandWt,
                                    ]
                                ];

                                $offer[] = $class_offer;
                            }
                        }
                    }
                }

                return $offer;

            });
        } else {
            foreach ($segments as $segment) {
                $result[] = $this->priceSegment($segment, $adults, $children, $infants);
            }
        }


        return $result;
    }

    private function _parseFlightResponse($result, $adults, $children, $infants)
    {
        // $flight_offers = [];
        $flight_offer = [];

        if ($result != null) {
            $xml = "";

            if (preg_match('/<xml>(.*?)<\/xml>/', $result, $match) == 1) {
                $xml = $match[1];
            }

            $xml = "<xml>" . $xml . "</xml>";

            $xmlObject = simplexml_load_string($xml);

            $flight_offer_id = 1;
            foreach ($xmlObject?->itin as $videcom_itinirary) {
                $bookable_seats = 9;
                $flight_offer_segment_id = 1;
                $segments = [];

                $flight_offer = [
                    "type" => "flight-offer",
                    "id" => $flight_offer_id,
                    'international' => $videcom_itinirary->attributes('', true)->international == 1 ? true : false,
                    "source" => "videcom",
                    "oneWay" => true,
                    'origin' => $videcom_itinirary->flt[0]->dep . '',
                    'destination' => $videcom_itinirary->flt[$videcom_itinirary->flt->count() - 1]->dep . '',
                    "lastTicketingDate" => "",
                    "numberOfBookableSeats" => "",
                    'itineraries' => [],
                ];

                $itiniraries = [];

                foreach ($videcom_itinirary->flt as $videcom_flight) {
                    $segment = [
                        'id' => $flight_offer_segment_id,
                        'departure' => [
                            'iataCode' => "" . $videcom_flight->dep,
                            'terminal' => "" . $videcom_flight->fltdet->depterm,
                            'at' => $videcom_flight->time->ddaylcl . "T" . $videcom_flight->time->dtimlcl,
                        ],
                        'arrival' => [
                            'iataCode' => "" . $videcom_flight->arr,
                            'terminal' => "" . $videcom_flight->fltdet->arrterm,
                            'at' => $videcom_flight->time->adaylcl . "T" . $videcom_flight->time->atimlcl,
                        ],
                        'carrierCode' => "" . $videcom_flight->fltdet->airid,
                        'number' => "" . $videcom_flight->fltdet->fltno,
                        'aircraft' => [
                            'code' => "" . $videcom_flight->fltdet->eqp,
                        ],
                        'operating' => [
                            'carrierCode' => "" . $videcom_flight->fltdet->airid,
                        ],
                        'duration' => "" . $videcom_flight->time->duration,
                        'numberOfStops' => "" . $videcom_flight->fltdet->stp,
                    ];

                    $flight_offer_segment_id++;

                    $segment_pricing = $this->priceSegment($segment, $adults, $children, $infants);

                    $segment['offers'] = $segment_pricing;

                    // return [$segment];
                    foreach ($segment_pricing as $s_p) {
                        if ($bookable_seats > $s_p['seats']) {
                            $bookable_seats = $s_p['seats'];
                        }
                    }

                    $segments[] = $segment;
                }

                $itiniraries[] = [
                    'duration' => "" . $videcom_itinirary->attributes('', true)->totalduration,
                    'segments' => $segments,
                ];

                $flight_offer['numberOfBookableSeats'] = $bookable_seats;

                $flight_offer['itineraries'] = $itiniraries;
                $flight_offer_id++;

                $flight_offer['travelerPricings'] = [];

                $traveler_index = 1;
                $travelerPricings = [];
                for ($i = 0; $i < $adults; $i++) {
                    $travelerPricings[] = [
                        'travelerId' => $traveler_index,
                        'fareOption' => 'STANDARD',
                        'travelerType' => 'ADULT',
                        'price' => [
                            "currency" => "EUR",
                            "total" => "355.34",
                            "base" => "255.00"
                        ],
                        'fareDetailsBySegment' => []
                    ];

                    $traveler_index++;
                }

                // for ($i = 0; $i < $offer['_meta']['passengers']['children']; $i++) {
                //     $offer['travelers'][] = [
                //         'index' => $traveler_index,
                //         'type' => 'CHILD',
                //         'name' => '',
                //     ];
                //     $traveler_index++;
                // }

                // for ($i = 0; $i < $offer['_meta']['passengers']['infants']; $i++) {
                //     $offer['travelers'][] = [
                //         'index' => $traveler_index,
                //         'type' => 'INFANT',
                //         'name' => '',
                //     ];
                //     $traveler_index++;
                // }

                $flight_offer['price'] = [
                    "currency" => "",
                    "total" => "355.34",
                    "base" => "255.00",
                    "fees" => [
                        [
                            "amount" => "0.00",
                            "type" => "SUPPLIER",
                        ],
                        [
                            "amount" => "0.00",
                            "type" => "TICKETING"
                        ],
                    ],
                    "grandTotal" => "355.34"
                ];
                // 
                // array_push($flight_offers, $flight_offer);
            }
        }

        // return $flight_offers;
        return $flight_offer;
    }

    private function _parseTowWayFlightResponse($results, $adults, $children, $infants)
    {
        // $flight_offers = [];
        $flight_offer = [];

        if ($results != null) {

            $segments = [];

            $itiniraries = [];

            foreach ($results as $result) {
                $xml = "";

                if (preg_match('/<xml>(.*?)<\/xml>/', $result, $match) == 1) {
                    $xml = $match[1];
                }

                $xml = "<xml>" . $xml . "</xml>";

                $xmlObject = simplexml_load_string($xml);

                $flight_offer_id = 1;
                foreach ($xmlObject?->itin as $videcom_itinirary) {
                    $bookable_seats = 9;
                    $flight_offer_segment_id = 1;

                    foreach ($videcom_itinirary->flt as $videcom_flight) {
                        $segment = [
                            'id' => $flight_offer_segment_id,
                            'departure' => [
                                'iataCode' => "" . $videcom_flight->dep,
                                'terminal' => "" . $videcom_flight->fltdet->depterm,
                                'at' => $videcom_flight->time->ddaylcl . "T" . $videcom_flight->time->dtimlcl,
                            ],
                            'arrival' => [
                                'iataCode' => "" . $videcom_flight->arr,
                                'terminal' => "" . $videcom_flight->fltdet->arrterm,
                                'at' => $videcom_flight->time->adaylcl . "T" . $videcom_flight->time->atimlcl,
                            ],
                            'carrierCode' => "" . $videcom_flight->fltdet->airid,
                            'number' => "" . $videcom_flight->fltdet->fltno,
                            'aircraft' => [
                                'code' => "" . $videcom_flight->fltdet->eqp,
                            ],
                            'operating' => [
                                'carrierCode' => "" . $videcom_flight->fltdet->airid,
                            ],
                            'duration' => "" . $videcom_flight->time->duration,
                            'numberOfStops' => "" . $videcom_flight->fltdet->stp,
                        ];

                        $flight_offer_segment_id++;

                        $segments[] = $segment;
                    }
                }
            }

            $segment_pricing = $this->priceSegments($segments, $adults, $children, $infants);

            $segment['offers'] = $segment_pricing;

            // return [$segment];
            foreach ($segment_pricing as $s_p) {
                if ($bookable_seats > $s_p['seats']) {
                    $bookable_seats = $s_p['seats'];
                }
            }

            $itiniraries[] = [
                'duration' => "" . $videcom_itinirary->attributes('', true)->totalduration,
                'segments' => $segments,
            ];

            $flight_offer['numberOfBookableSeats'] = $bookable_seats;

            $flight_offer['itineraries'] = $itiniraries;
            $flight_offer_id++;

            $flight_offer['travelerPricings'] = [];

            $traveler_index = 1;
            $travelerPricings = [];
            for ($i = 0; $i < $adults; $i++) {
                $travelerPricings[] = [
                    'travelerId' => $traveler_index,
                    'fareOption' => 'STANDARD',
                    'travelerType' => 'ADULT',
                    'price' => [
                        "currency" => "EUR",
                        "total" => "355.34",
                        "base" => "255.00"
                    ],
                    'fareDetailsBySegment' => []
                ];

                $traveler_index++;
            }

            // for ($i = 0; $i < $offer['_meta']['passengers']['children']; $i++) {
            //     $offer['travelers'][] = [
            //         'index' => $traveler_index,
            //         'type' => 'CHILD',
            //         'name' => '',
            //     ];
            //     $traveler_index++;
            // }

            // for ($i = 0; $i < $offer['_meta']['passengers']['infants']; $i++) {
            //     $offer['travelers'][] = [
            //         'index' => $traveler_index,
            //         'type' => 'INFANT',
            //         'name' => '',
            //     ];
            //     $traveler_index++;
            // }

            $flight_offer['price'] = [
                "currency" => "",
                "total" => "355.34",
                "base" => "255.00",
                "fees" => [
                    [
                        "amount" => "0.00",
                        "type" => "SUPPLIER",
                    ],
                    [
                        "amount" => "0.00",
                        "type" => "TICKETING"
                    ],
                ],
                "grandTotal" => "355.34"
            ];
            // 
            // array_push($flight_offers, $flight_offer);
        }

        // return $flight_offers;
        return $flight_offer;
    }

    //  ==> Parsers
    private function parseFlightScheule($index, $item)
    {
        switch ($this->aeroToken->iata) {
            case 'YI':
                return [
                    'index' => $index,
                    'iata' => $this->aeroToken->iata,
                    'flight_number' => $item->FLTNO . '',
                    'flight_date' => date('Y-m-d', strtotime(str_replace('/', '-', $item->Flt_Date . ''))),
                    'departure' => [
                        'airport' => $item->DEPART . '',
                        'datetime' => date('Y-m-d H:i', strtotime(str_replace('/', '-', $item->LVTIME . ''))),
                    ],
                    'arrival' => [
                        'airport' => $item->DESTIN . '',
                        'datetime' => date('Y-m-d H:i', strtotime(str_replace('/', '-', $item->ARTIME . ''))),
                    ],
                    'carrier' => [
                        'number' => $item->REF . '',
                    ],
                    '@no_pax' => $item->NoPAX . '',
                    'sale' => !$item->RestrictSales . '',
                ];
            case 'UZ':
                return [
                    'index' => $index,
                    'iata' => $this->aeroToken->iata,
                    'flight_number' => $item->FLTNO . '',
                    'flight_date' => $item->Flt_Date . '',
                    'departure' => [
                        'airport' => $item->DEPART . '',
                        'datetime' => date('Y-m-d H:i', strtotime(str_replace('/', '-', $item->DepartTime1 . ''))),
                    ],
                    'arrival' => [
                        'airport' => $item->DESTIN . '',
                        'datetime' => date('Y-m-d H:i', strtotime(str_replace('/', '-', $item->ARTIME . ''))),
                    ],
                    'carrier' => [
                        'number' => $item->REF . '',
                    ],
                    '@no_pax' => null,
                    'sale' => null,
                ];
            case 'YL':
                return [
                    'index' => $index,
                    'iata' => $this->aeroToken->iata,
                    'flight_number' => $item->FltNo . '',
                    'flight_date' => date('Y-m-d', strtotime(str_replace('/', '-', $item->Flt_Date . ''))),
                    'departure' => [
                        'airport' => $item->Depart . '',
                        'datetime' => date('Y-m-d H:i', strtotime(str_replace('/', '-', $item->LVTIME . ''))),
                    ],
                    'arrival' => [
                        'airport' => $item->Destin . '',
                        'datetime' => date('Y-m-d H:i', strtotime(str_replace('/', '-', $item->ARTIME . ''))),
                    ],
                    'carrier' => [
                        'number' => $item->REF . '',
                    ],
                    '@no_pax' => $item->NoPAX . '',
                    'sale' => !$item->RestrictSales . '',
                ];
            default:
                return [
                    'index' => $index,
                    'iata' => $this->aeroToken->iata,
                    'flight_number' => $item->FLTNO . '',
                    'flight_date' => date('Y-m-d', strtotime(str_replace('/', '-', $item->Flt_Date . ''))),
                    'departure' => [
                        'airport' => $item->DEPART . '',
                        'datetime' => date('Y-m-d H:i', strtotime(str_replace('/', '-', $item->LVTIME . ''))),
                    ],
                    'arrival' => [
                        'airport' => $item->DESTIN . '',
                        'datetime' => date('Y-m-d H:i', strtotime(str_replace('/', '-', $item->ARTIME . ''))),
                    ],
                    'carrier' => [
                        'number' => $item->REF . '',
                    ],
                    '@no_pax' => $item->NoPAX . '',
                    'sale' => !$item->RestrictSales . '',
                ];
        }
    }
}


