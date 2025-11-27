<?php

namespace App\Core;

use App\Models\HotelToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;

class Hotel3tnOld 
{
    private $token;
    private $client;
    public function __construct(HotelToken $token)
    {
        $this->token = $token;
        $this->_build();
    }

    public function _build()
    {
        $this->client = Http::baseUrl($this->token->data['url'])
            ->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Api-key' => $this->token->data['api_key'],
                'Login' => $this->token->data['login'],
                'Password' => $this->token->data['password'],
            ]);
    }

    public function get_locations(array $query)
    {
        $lang_header = request()->header("Accept-Language", 'fr_FR');
        $cache_key = $this->token->id . '-' . md5(json_encode($query)) . "_locations";

        // if (count(cache()->get($cache_key, [])) = 0) {
        //     cache()->forget($cache_key);
        // }

        // $result = cache()->remember($cache_key, now()->addMinutes(30), function () use ($query) {
            $status = 200;
            $data = [];
            $success = true;

            $response = $this->client->post("hotels-api?method=autocomplete", [
                'language' => $lang_header
            ]);

            if (!$response->json("error")) {
                $status = 200;
                $data = $response->json("response");
            } else {
                $status = 404;
                $success = false;
            }

            foreach ($data as &$row) {
                $location = [
                    'country' => 'TUN',
                ];

                $location['id'] = strtolower(explode(',', $row['label'])[0]);
                $location['name'] = $row['label'];

                if ($row['category'] == "VILLE") {
                    $location['category'] = 'location';
                };

                if ($row['category'] == "HOTEL") {
                    $location['category'] = 'hotel';
                };

                $row = $location;
            }

            if (isset($query['search'])) {
                $data = collect($data)->where('label', 'like', '%' . $query['search'] . '%')->toArray();
            }

            return [
                'success' => $success,
                'status' => $status,
                'data' => $data
            ];
        // });

        // return $result;
    }

    public function get_availabilities(array $query)
    {
        $rooms = [];

        $lang_header = request()->header("Accept-Language", 'fr_FR');

        foreach ($query['rooms'] as $index => $room) {
            $rooms[$index + 1] = [
                'adult' => $room['adults'],
                'child' => [
                    'value' => $room['children'],
                    'age' => "",
                ],
            ];

            if ($room['children'] > 0) {
                $rooms[$index + 1]['child']['age'] = $room['ages'];
            }
        }

        $request_body = [
            'checkIn' => date('Y-m-d', strtotime($query['checkin'])),
            'checkOut' => date('Y-m-d', strtotime($query['checkout'])),
            'city' => $query['location'],
            'hotelName' => '',
            'boards' => [],
            'rating' => [],
            'occupancies' => $rooms,
            'language' => $lang_header,
            'onlyAvailableHotels' => true
        ];

        $cache_key = $this->token->id . '_' . md5(json_encode($request_body)) . "_availabilities";

        // $result = cache()->remember($cache_key, now()->addMinutes(10), function () use ($request_body) {
            $status = 200;
            $data = [];
            $success = true;

            $response = $this->client->post("hotels-api?method=availability", $request_body);

            if (!$response->json("error")) {
                $status = 200;
                $data = $response->json("response");

                # Cache Offers
                foreach ($data as &$offer) {
                    $offer_cache_key = Crypt::encryptString($this->token->id . '-' . $offer['searchCode']);
                    $offer['id'] = $offer_cache_key;

                    cache()->put($offer_cache_key, $offer, now()->addMinutes(60));
                }
            } else {
                $status = 404;
                $success = false;
            }

            return [
                'success' => $success,
                'status' => $status,
                'data' => $data
            ];
        // });


        // return $result;
    }

    public function get_hotel_details(array $query) {
        $lang_header = request()->header("Accept-Language", 'fr_FR');

        $cache_key = $this->token->id . '_' . md5(json_encode($query)) . "_hotel_details";

        // $result = cache()->remember($cache_key, now()->addMinutes(10), function () use ($query) {
            $status = 200;
            $data = [];
            $success = true;

            $response = $this->client->post("hotels-api?method=hotelDetails", [
                'hotelId' => $query['hotel_id'],
                'cityId' => $query['city_id'],
                'source' => $query['source'],
                'language' => $lang_header,
            ]);

            if (!$response->json("error")) {
                $status = 200;
                $data = $response->json("response");
            } else {
                $status = 404;
                $success = false;
            }

            return [
                'success' => $success,
                'status' => $status,
                'data' => $data
            ];
        // });
    }
    public function check_hotel_rate(array $query) {
        $lang_header = request()->header("Accept-Language", 'fr_FR');

        $cache_key = $this->token->id . '_' . md5(json_encode($query)) . "_hotel_details";

        // $result = cache()->remember($cache_key, now()->addMinutes(10), function () use ($query) {
            $status = 200;
            $data = [];
            $success = true;

            $response = $this->client->post("hotels-api?method=checkRate", [
                'rooms' => $query['rooms'],
                'language' => $lang_header,
            ]);

            if (!$response->json("error")) {
                $status = 200;
                $data = $response->json("response");
            } else {
                $status = 404;
                $success = false;
            }

            return [
                'success' => $success,
                'status' => $status,
                'data' => $data
            ];
        // });
    }

}