<?php

namespace App\Http\Controllers\Api\V3\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelToken;
use App\Models\Order;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class HotelController extends Controller
{
    public function get_locations()
    {
        $response_code = 200;

        $response = [
            'meta' => [
                'count' => 0,
                'status' => true,
                'message' => 'OK',
            ],
            'data' => [
                [
                    'name' => 'Sousse',
                    'type' => 'location',
                ],
                [
                    'name' => 'Hammamet',
                    'type' => 'location',
                ],
            ],
        ];

        $hotel_tokens = HotelToken::all();

        $search = request()->get('search', '');

 

        // try {
            foreach ($hotel_tokens as $hotel_token) {
                $batch = $hotel_token->build()->getSearchSuggestions($search);

                if ($batch['success']) {
                    array_push($response['data'], ...$batch['data']);
                }

            }

            $response['meta']['count'] = count($response['data']);
        // } catch (Exception $ex) {
        //      $response['meta']['status'] = false;
        //      $response_code = 500;
        // } finally {
        //      return response()->json($response, $response_code);
        // }

         return response()->json($response, $response_code);
    }

     public function get_availability(Request $request)
    {
        $response_code = 200;

        $response = [
            'meta' => [
                'count' => 1,
                'status' => true,
                'message' => 'OK',
            ],
            'data' => [],
        ];

        $hotel_token = HotelToken::first();
        $batch = $hotel_token->build()->searchHotels($request->all());
        // return $batch;

        // $response['meta']['count'] += count($batch['data']);
        
        array_push($response['data'], ...$batch['data']['hotels']);

        return response()->json($response, $response_code);
    }

    public function get_availability_old(Request $request)
    {
        $response_code = 200;

        $response = [
            'meta' => [
                'count' => 0,
                'status' => true,
                'message' => 'OK',
            ],
            'data' => [],
        ];

        // try {
        $hotel_tokens = HotelToken::all();
        foreach ($hotel_tokens as $token) {
            $batch = $token->build()->searchHotels($request->all());

            // return $batch;
            array_push($response['data'], ...$batch['data']);
            if ($batch['success']) {
                // foreach ($batch['data'] as &$data) {
                //     if (!cache()->has("ar_" . md5($data['hotel']['hotelName']))) {
                //         \App\Jobs\TranslateTextJob::dispatch($data['hotel']['hotelName'], 'ar')->onQueue('default');
                //     }
                //     $hotel_name = cache()->get("ar_" . md5($data['hotel']['hotelName']), $data['hotel']['hotelName']);
                //     $data['hotel']['hotelName'] = $hotel_name;

                //     foreach ($data['rooms'] as &$rooms) {
                //         foreach ($rooms as &$room) {

                //             if (!cache()->has("ar_" . md5($room['name']))) {
                //                 \App\Jobs\TranslateTextJob::dispatch($room['name'], 'ar')->onQueue('default');
                //             }
                //             $room_name = cache()->get("ar_" . md5($room['name']), $room['name']);
                //             $room['name'] = $room_name;

                //         }
                //     }
                // }
                // array_push($response['data'], ...$batch['data']);
            }
        }

          return response()->json($response, $response_code);
        // $response['meta']['count'] = count($response['data']);
        // } catch (Exception $e) {
        // $response['meta']['status'] = false;
        // $response_code = 500;
        // } finally {
        //     return response()->json($response, $response_code);
        // }
    }

    /**
     * Retrieve detailed information for a specific hotel.
     *
     * @param Request $request The incoming request containing the hotel_id.
     * @return \Illuminate\Http\JsonResponse JSON response with hotel details.
     * @throws \Illuminate\Validation\ValidationException If the request validation fails.
     */

    public function get_hotel_details(Request $request)
    {
        $request->validate([
            'offer' => 'required',
        ]);

        $cached_offer = cache('hotel-context:' . $request->offer, null);

        if ($cached_offer == null) {
            return response()->json([
                'meta' => [
                    'status' => false,
                    'message' => 'Offer not found',
                ],
                'data' => [],
            ], 404);
        }

        $hotel_token = HotelToken::find($cached_offer['tokenId']);

        // return $cached_offer;
        $result = $hotel_token->build()->getHotelDetails($cached_offer['data']);

        return response()->json($result);

        // return response()->json($cached_offer);
        // // Check if offer not existed
        // if(cache()->get($request->offer['id'], null) === null) {
        //     return response()->json([
        //         'meta' => [
        //             'status' => false,
        //             'message' => 'Offer not found',
        //         ],
        //         'data' => [],
        //     ], 404);
        // }

        // $offer = cache()->get($request->offer['id']);   

        // $decrypted_id = Crypt::decryptString($offer['id']);
        // $hotel_token_id = explode('_', $decrypted_id)[0];
        // $hotel_token = HotelToken::find($hotel_token_id);

        // $query = [
        //     'city_id' => $offer['hotel']['cityId'],
        //     'source' => $offer['source'],
        // ];

        // $hotel_details = $hotel_token->build()->getHotelDetails($offer['hotel']['hotelId'], $query);

        // return response()->json([
        //     'meta' => [
        //         'status' => true,
        //         'message' => 'OK',
        //     ],
        //     'data' => $hotel_details['data'],
        // ], 200);


    }

    public function check_rate(Request $request)
    {
        $request->validate([
            'offer' => 'required',
            'rooms' => 'required|array',
        ]);

        // Check if offer not existed
        // if(cache()->get($request->offer['id'], null) === null) {
        //     return response()->json([
        //         'meta' => [
        //             'status' => false,
        //             'message' => 'Offer not found',
        //         ],
        //         'data' => [],
        //     ], 404);
        // }

        $offer = cache()->get('hotel-context:' . $request->offer);

        // return $offer;

        // $decrypted_id = Crypt::decryptString($offer['id']);
        // $hotel_token_id = explode('_', $decrypted_id)[0];
        $hotel_token_id = $offer['tokenId'];
        $hotel_token = HotelToken::find($hotel_token_id);

        // get rate keys only
        $rate_keys = [];
        foreach ($request->rooms as $room) {
            // foreach ($room as $rate) {
            $rate_keys[] = [
                'ratekey' => $room['rateKey'],
            ];
            // }
        }

        $check_rate = $hotel_token->build()->checkRates($request->offer, $rate_keys);

        return response()->json([
            'meta' => [
                'status' => true,
                'message' => 'OK',
                'code' => $check_rate['status'],
            ],
            'data' => $check_rate['data'],
        ], 200);
    }
    public function create_order(Request $request)
    {
        $request->validate([
            'offer' => 'required',
            'bookingUuid' => 'required',
            'order' => 'required|array',
            // 'payment' => 'required|array',
        ]);

        // return $request->all();

        $offer = cache()->get('hotel-context:' . $request->offer);

        if ($offer == null) {
            return response()->json([
                'meta' => [
                    'status' => false,
                    'message' => 'Offer not found',
                ],
                'data' => [],
            ], 404);
        }

        $hotel_token = HotelToken::find($offer['tokenId']);

        // return $cached_offer;
        $result = $hotel_token->build()->createBooking($request->offer, $request->bookingUuid, $request->order ?? [], $request->payment);

        

        return $result;
    }

    public function get_orders(Request $request)
    {

    }

    public function cancel_order(Request $request)
    {
        $request->validate([
            'order_number' => 'required',
        ]);

        $order = Order::where('number', $request->order_number)->first();

        foreach ($order->order_items as $order_item) {
            if ($order_item->type == 'hotel') {
                $hotel_token = HotelToken::where('code', $order_item->provider)->first();

                $cancellation = $hotel_token->build()->cancelBooking($order_item->reference, $order_item->toArray());
            }

            return $cancellation;
        }
    }

    public function get_balance(Request $request)
    {

    }
}
