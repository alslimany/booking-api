<?php

use App\Http\Controllers\Api\V3\Air\FlighOfferSearchController;
use App\Http\Controllers\Api\V3\Air\OmraOfferSearchController;
use App\Http\Controllers\Api\V3\Air\SeatMapController;
use App\Http\Controllers\Api\V3\AirportContoller;
use App\Http\Controllers\Api\V3\Hotel\HotelController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

#
use Codewithkyrian\Transformers\Transformers;
use function Codewithkyrian\Transformers\Pipelines\pipeline;
#

Route::group([
   'prefix' => 'v3',
   'middleware' => ['auth:sanctum', 'log-api'],
], function () {
   Route::group([
      'prefix' => 'air',
   ], function () {
      Route::get('flight-offers', [FlighOfferSearchController::class, 'flight_offers']);
      Route::get('availability/flight-availabilities', [FlighOfferSearchController::class, 'flight_availabilities']);
      Route::post('flight-offers/pricing', [FlighOfferSearchController::class, 'flight_offers_pricing']);
      Route::post('flight-offers/order', [FlighOfferSearchController::class, 'create_order']);

      Route::get('seat-map', [SeatMapController::class, 'get_seat_map']);
   });

   Route::group([
      'prefix' => 'omra',
   ], function () {
      Route::get('flight-offers', [OmraOfferSearchController::class, 'flight_offers']);
   });

   Route::group([
      'prefix' => 'hotel',
   ], function () {
      Route::get('locations', [HotelController::class, 'get_locations']);
      Route::get('availabilities', [HotelController::class, 'get_availability']);
      Route::post('hotel-details', [HotelController::class, 'get_hotel_details']);
      Route::post('check-rate', [HotelController::class, 'check_rate']);
      Route::post('order', [HotelController::class, 'create_order']);
      Route::get('get-orders', [HotelController::class, 'get_orders']); // not implemented
      Route::post('cancel-order', [HotelController::class, 'cancel_order']); // not implemented
      Route::get('balance', [HotelController::class, 'get_balance']); // not implemented
   });

   Route::group([
      'prefix' => 'utils',
   ], function () {
      Route::post('translate', function (Request $request) {
         Transformers::setup()->setCacheDir(cacheDir: "/www/wwwroot/booking-api.atom.ly/.transformers-cache")->apply();
         $translationPipeline = pipeline("translation", 'Xenova/nllb-200-distilled-600M');


         $output = $translationPipeline(
            "FLY ELITE",
            maxNewTokens: 256,
            tgtLang: 'arb_Arab'
         );

         return $output;
         // return phpinfo();
         return trim($output[0]["translation_text"]);
      });

      Route::group(['prefix' => 'airport'], function () {
         Route::get('{airport}/arrivals', [AirportContoller::class, 'list_arrivals']);
      });
   });
});
