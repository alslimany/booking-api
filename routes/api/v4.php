<?php

use App\Http\Controllers\Api\V4\Air\FlighOfferSearchController;

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

#
use Codewithkyrian\Transformers\Transformers;
use function Codewithkyrian\Transformers\Pipelines\pipeline;
#

Route::group([
   'prefix' => 'v4',
   'middleware' => ['auth:sanctum', 'log-api'],
], function () {
   Route::group([
      'prefix' => 'air',
   ], function () {

      Route::get('flight-search', [FlighOfferSearchController::class, 'flight_search']);

      Route::get('flight-offers', [FlighOfferSearchController::class, 'flight_offers']);
      Route::get('availability/flight-availabilities', [FlighOfferSearchController::class, 'flight_availabilities']);
      Route::post('flight-offers/pricing', [FlighOfferSearchController::class, 'flight_offers_pricing']);
      Route::post('flight-offers/order', [FlighOfferSearchController::class, 'create_order']);

    
   });

 

//    Route::group([
//       'prefix' => 'utils',
//    ], function () {
//       Route::post('translate', function (Request $request) {
//          Transformers::setup()->setCacheDir(cacheDir: "/www/wwwroot/booking-api.atom.ly/.transformers-cache")->apply();
//          $translationPipeline = pipeline("translation", 'Xenova/nllb-200-distilled-600M');


//          $output = $translationPipeline(
//             "FLY ELITE",
//             maxNewTokens: 256,
//             tgtLang: 'arb_Arab'
//          );

//          return $output;
//          // return phpinfo();
//          return trim($output[0]["translation_text"]);
//       });

//       Route::group(['prefix' => 'airport'], function () {
//          Route::get('{airport}/arrivals', [AirportContoller::class, 'list_arrivals']);
//       });
//    });
});
