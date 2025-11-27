<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\V5\Air\FlighOfferSearchController;

Route::group([
   'prefix' => 'v5',
   'middleware' => ['auth:sanctum', 'log-api'],
], function () {
   Route::group([
      'prefix' => 'air',
   ], function () {
      Route::get('flight-offers', [FlighOfferSearchController::class, 'flight_offers']);
   });

 
});
