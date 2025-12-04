<?php

namespace App\Core;

use App\Core\IHotel;
use App\Models\HotelToken;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;

class Hotel3tn implements IHotel
{
    private HotelToken $token;
    private PendingRequest $client;

    /**
     * The constructor receives the provider's configuration.
     */
    public function __construct(HotelToken $token)
    {
        $this->token = $token;
        $this->_build();
    }

    /**
     * Sets up the HTTP client with the base URL and authentication headers.
     */
    private function _build(): void
    {
        $this->client = Http::baseUrl($this->token->data['url'])
            ->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Api-key' => $this->token->data['api_key'],
                'Login' => $this->token->data['login'],
                'Password' => $this->token->data['password'],
                'Content-Type' => 'application/json'
            ]);
    }

    /**
     * Get location and hotel name suggestions for a search query.
     */
    public function getSearchSuggestions(string $searchText): array
    {
        $lang_header = request()->header("Accept-Language", 'fr_FR');

        $response = $this->client->post('hotels-api?method=autocomplete', [
            'termSearch' => $searchText,
            'language' => $lang_header
        ]);

        if ($response->json('error')) {
            return ['success' => false, 'status' => 404, 'data' => [], 'message' => $response->json('msg')];
        }

        $suggestions = [];
        foreach ($response->json('response', []) as $row) {
            $suggestions[] = [
                'name' => $row['label'],
                'type' => ($row['category'] == "VILLE") ? 'location' : 'hotel',
            ];
        }

        return ['success' => true, 'status' => 200, 'data' => $suggestions];
    }

    /**
     * Perform a full search for available hotels.
     * This creates the booking context for subsequent calls.
     */
    public function searchHotels(array $searchData): array
    {
        // 1. Transform standardized input to provider's format
        $rooms = [];
        foreach ($searchData['rooms'] as $index => $room) {
            $rooms[$index + 1] = [
                'adult' => $room['adults'],
                'child' => [
                    'value' => $room['children'] ?? 0,
                    'age' => ($room['children'] > 0 && isset($room['ages'])) ? implode(',', $room['ages']) : [11],
                ],
            ];
        }

        $requestBody = [
            'checkIn' => date('Y-m-d', strtotime($searchData['checkin'])),
            'checkOut' => date('Y-m-d', strtotime($searchData['checkout'])),
            'city' => $searchData['location'],
            'hotelName' => '',
            'boards' => [],
            'rating' => [],
            'hotelId' => [],
            'occupancies' => $rooms,
            'language' => request()->header("Accept-Language", 'fr_FR'),
            'onlyAvailableHotels' => false,
            'channel' => 'b2b',
            'filtreSearch' => [],
        ];

        // 2. Call the provider's API
        $response = $this->client->post("hotels-api?method=availability", $requestBody);

        // return $response->json();
        if ($response->json("error")) {
            return ['success' => false, 'status' => 400, 'data' => [], 'message' => $response->json('msg')];
        }

        $hotelsFromProvider = $response->json('response', []);

        if (empty($hotelsFromProvider)) {
            return ['success' => true, 'status' => 200, 'data' => ['hotels' => []]];
        }

        // 3. Create and cache the "Booking Context"
        foreach ($hotelsFromProvider as &$hotelData) {
            $contextId = (string) Str::uuid();
            $searchCode = $hotelsFromProvider[0]['searchCode'];

            $contextData = [
                'provider' => '3tn',
                'tokenId' => $this->token->id,
                'searchCode' => $searchCode,
                'tokenForBook' => null, // Will be filled by checkRates
                'data' => $hotelData,
            ];

            $hotelData['uuid'] = $contextId;

            cache()->put('hotel-context:' . $contextId, $contextData, now()->addMinutes(30));
        }

        // 4. Transform provider's response to your standardized format
        $standardizedHotels = [];
        foreach ($hotelsFromProvider as $hotelData) {

            // $offer_cache_key = Crypt::encryptString($this->token->id . '-' . $hotelData['searchCode']);
            // $offer['id'] = $offer_cache_key;

            $standardizedHotels[] = [
                'uuid' => $hotelData['uuid'],
                // 'id' => $hotelData['hotel']['hotelId'],
                'name' => $hotelData['hotel']['hotelName'],
                'rating' => (int) $hotelData['hotel']['ratingId'],
                'image' => $hotelData['hotel']['thumbImage'],
                'minPrice' => $hotelData['hotel']['minPriceRoom1'],
                'currencyCode' => 'TND',
                'rooms' => $this->standardizeRooms($hotelData['rooms']),
                // Bundle provider-specific data needed for the getHotelDetails call
                'provider_data' => [
                    'cityId' => $hotelData['hotel']['cityId'],
                    'source' => $hotelData['source'],
                ],
                'original' => $hotelData
            ];

            // cache()->put('hotel-uuid:' . $offer_cache_key, $hotelData, now()->addMinutes(30));

        }

        // 5. Return the standardized response with the contextId
        return [
            'success' => true,
            'status' => 200,
            'meta' => [
                'count' => count($standardizedHotels),
            ],
            'data' => [
                'contextId' => $contextId,
                'hotels' => $standardizedHotels,
            ]
        ];
    }

    /**
     * Get detailed information for a single hotel.
     */
    public function getHotelDetails(array $offer, array $options = []): array
    {
        // if (!isset($options['cityId']) || !isset($options['source'])) {
        //     return ['success' => false, 'status' => 400, 'message' => 'Missing required provider options (cityId, source).'];
        // }
        $hotelId = $offer['hotel']['hotelId'];
        $cityId = $offer['hotel']['cityId'];
        $source = $offer['source'];

        $response = $this->client->post("hotels-api?method=hotelDetails", [
            'hotelId' => $hotelId,
            'cityId' => $cityId,
            'source' => $source,
            'language' => request()->header("Accept-Language", 'fr_FR'),
        ]);

        if ($response->json("error")) {
            return ['success' => false, 'status' => 404, 'message' => $response->json('msg')];
        }

        $details = $response->json('response');
        $standardizedDetails = [
            'id' => $details['hotel']['hotelId'],
            'name' => $details['hotel']['hotelName'],
            'description' => html_entity_decode($details['hotelDetails']['description']),
            'rating' => $details['hotel']['ratingId'],
            'score' => $details['hotel']['score'],
            'address' => [
                'country' => $details['hotel']['countryName'],
                'city' => $details['hotel']['cityName'],
                'zone' => $details['hotel']['zoneName'],
                'phone' => $details['hotel']['phone'],
            ],
            'location' => [
                'latitude' => $details['hotel']['latitude'],
                'longitude' => $details['hotel']['longitude'],
            ],
            'images' => $details['gallery'],
            'amenities' => $details['amunities'],
            'options' => $details['options'],
        ];

        return ['success' => true, 'status' => 200, 'data' => $standardizedDetails];
    }

    /**
     * Re-validates the price for selected rooms before booking.
     */
    public function checkRates(string $contextId, array $rateKeys): array
    {
        $context = cache()->get('hotel-context:' . $contextId);
        if (!$context || $context['provider'] !== '3tn') {
            return ['success' => false, 'status' => 404, 'message' => 'Invalid or expired search session.'];
        }

        $response = $this->client->post("hotels-api?method=checkRate", [
            'searchCode' => $context['searchCode'],
            'rooms' => $rateKeys, // Expects format: [['ratekey' => '...']]
            'language' => request()->header("Accept-Language", 'fr_FR'),
        ]);

        if ($response->json("error")) {
            return ['success' => false, 'status' => 400, 'message' => $response->json('msg')];
        }

        // Update context with the booking token
        $context['tokenForBook'] = $response->json('tokenForBook');
        cache()->put('hotel-context:' . $contextId, $context, now()->addMinutes(30));

        $hotelData = $response->json('response.0'); // Response is an array with one hotel

        $bookingUuid = (string) Str::uuid();
        $standardizedResponse = [
            'offerUuid' => $contextId,
            'bookingUuid' => $bookingUuid,
            'hotel' => [
                'id' => $hotelData['hotel']['hotelId'],
                'name' => $hotelData['hotel']['hotelName'],
            ],
            'rooms' => $this->standardizeRooms($hotelData['rooms']),
            'deadline' => $hotelData['deadline'],
            'cancellationPolicies' => $hotelData['rooms'][0][0]['cancellationPolicies'] ?? [], // Example policy
        ];

        $standardizedResponse['bookingToken'] = $context['tokenForBook'];
        // $standardizedResponse['contextId'] = $contextId;

        cache()->put('booking-context:' . $bookingUuid, $standardizedResponse, now()->addMinutes(30));

        return ['success' => true, 'status' => 200, 'data' => $standardizedResponse];
    }

    /**
     * Creates the final booking.
     */
    public function createBooking(string $contextId, string $bookingUuid, array $guestDetails, array $paymentDetails): Order
    {
        // To be implemented.

        $context = cache()->get('hotel-context:' . $contextId);
        // return [
        //     'contextId' => $contextId,
        //     'context' => $context,
        //     'guestDetails' => $guestDetails,
        // ];

        $rooms = [];
        foreach ($guestDetails as $room) {
            $rooms[] = [
                'ratekey' => $room['room']['rateKey'],
                'supplements' => [],
                'paxes' => $room['guests']
            ];
        }



        $response = $this->client->post("hotels-api?method=book", [
            'language' => 'fr-FR',
            'recommandations' => '',
            'searchCode' => $context['searchCode'],
            'tokenForBook' => $context['tokenForBook'],
            'rooms' => $rooms,
            'payment' => [
                'card' => '',
                'ccv' => '',
                'expire' => '',
            ],
            'customer' => [
                'firstName' => 'Abdullah',
                'lastName' => 'Ishtiwy',
                'email' => 'alslimany@gmail.com',
                'mobile' => '218911388788',
                'country' => 'Libya',
                'city' => 'Tripoli',
            ],
        ]);

        if ($response->json("error")) {
            // return ['success' => false, 'status' => 400, 'message' => $response->json('msg')];
            abort(400, $response->json('errorMessage'));
        }

        $response = $response->json('response');

        $order = new Order;
        $order->number = get_next_order_number();
        $order->owner_type = "App\Models\HotelToken";
        $order->owner_id = $this->token->id;
        $order->status = $response['confirmed'] ? "confirmed" : "pending";
        $order->issued_at = now();
        $order->save();



        $order->order_items()->create([
            'order_id' => $order->id,
            'type' => 'hotel',
            'provider' => $this->token->code,
            'reference' => $response['bookingId'],
            'price' => $response['totalPurchase'],
            'taxes' => 0,
            'total' => $response['totalPurchase'],
            'currency_code' => $response['currency'],
            'exchange_rate' => 1,
            'item' => $response,
            'net_commission' => 0,
            'agent_commission' => 0,
            'remaning' => 0,
            'paid' => true,
            'status' => $response['confirmed'] ? "confirmed" : "pending",

        ]);

        $order->order_items;
        return $order;
    }

    /**
     * Retrieves a list of bookings made by the customer.
     */
    public function getBookingList(array $filterData): array
    {
        $from = $filterData['fromDate'];
        $to = $filterData['toDate'];
        $bookingId = $filterData['bookingId'] ?? null;

        $response = $this->client->post("hotels-api?method=bookingList", [
            'fromDate' => $from,
            'toDate' => $to,
            'bookingId' => $bookingId,
        ]);

        return $response->json('response');
        // To be implemented.
        // 1. Build request body for 'getBookings' or 'bookingList' method.
        // 2. Make API call.
        // 3. Return a standardized list of bookings.
        return ['success' => false, 'status' => 501, 'message' => 'Not Implemented'];
    }

    /**
     * Cancels an existing booking.
     */
    public function cancelBooking(string $bookingId, array $options = []): array
    {
        // $response = $this->client->post("hotels-api?method=cancel", [
        //   'bookingId' => $bookingId,
        //   'bookingSource' => '',
        // ]);

        $from = date('Y-m-d', strtotime($options['created_at'] .  ' -1 day'));
        $to = date('Y-m-d', strtotime($options['created_at']  . ' +1 day'));
        $source = "";

        $bookingList = $this->getBookingList(filterData: [
            'fromDate' => $from,
            'toDate' => $to,
            'bookingId' => $bookingId
        ]);


        foreach ($bookingList as $booking) {
            if ($booking['bookingId'] == $bookingId) {
                $source = $booking['bookingSource'];
            }
        }

        $response = $this->client->post("hotels-api?method=cancel", [
            'bookingId' => $bookingId,
            'bookingSource' => $source,
        ]);

        return $response->json();

        // To be implemented.
        // 1. Validate that $options contains 'bookingSource'.
        // 2. Build request body for 'cancel' method.
        // 3. Make API call.
        // 4. Return standardized confirmation.
        return ['success' => false, 'status' => 501, 'message' => 'Not Implemented'];
    }

    /**
     * Get the account balance
     */
    public function getBalanace(): array
    {
        $response = $this->client->post("hotels-api?method=creditCheck", []);

        return $response->json();
    }
    /**
     * Helper function to standardize the complex room structure for frontend grouping.
     * Each group represents a room (as requested), and contains its guests and available rate options.
     */
    private function standardizeRooms(array $providerRooms): array
    {
        $standardGroups = [];
        foreach ($providerRooms as $roomOptions) {
            if (empty($roomOptions))
                continue;

            // Get guests info from the first option (all options in the group share the same guests)
            $firstOption = $roomOptions[0];
            $guests = [
                'roomIndex' => $firstOption['roomIndex'],
                'adults' => (int) ($firstOption['paxes']['adult'] ?? 0),
                'children' => (int) ($firstOption['paxes']['child']['value'] ?? 0),
                'children_ages' => ($firstOption['paxes']['child']['age'] ?? ""),
            ];

            // List all available rate options for this room
            $rates = [];
            foreach ($roomOptions as $option) {
                // $uuid = Crypt::encryptString($option['rateKey']);
                $uuid = $option['rateKey'];

                $rates[] = [
                    'uuid' => $uuid,
                    'refundable' => ($option['rateClass'] == 'NOR'),
                    'noShow' => $option['noShow'],
                    'rateKey' => $option['rateKey'],
                    'name' => $option['name'],
                    'boardName' => $option['boardName'],
                    'price' => $option['price'],
                    'currency' => $option['currency'],
                    'available' => $option['available'],
                    'cancellationPolicies' => $option['cancellationPolicies'],
                    'supplements' => $option['supplements'],
                    'notes' => $option['notes'],
                ];

                // Put room in cache using uuid
                // cache()->put($uuid, $option);
            }

            $standardGroups[] = [
                'guests' => $guests, // Room guest info
                'rates' => $rates,   // All rate options for this room group
            ];
        }
        return $standardGroups;
    }
}