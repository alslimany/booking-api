<?php


use App\Http\Controllers\Api\V2\Air\FlighOfferSearchController;
use App\Http\Controllers\Api\V2\GigaEsimController;
use App\Http\Controllers\V2\SalesReportController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

Route::group([
   'prefix' => 'v2',
   'middleware' => ['auth:sanctum', 'log-api'],
], function () {

   Route::group([
      'prefix' => 'air',
   ], function () {
      Route::get('flight-offers', [FlighOfferSearchController::class, 'flight_offers']);
      Route::post('flight-offers/pricing', [FlighOfferSearchController::class, 'flight_offers_pricing']);

      Route::get('round-way-flight-offers', [FlighOfferSearchController::class, 'round_way_flight_offers']);
   });

   Route::group([
      'prefix' => 'services',
   ], function () {

      Route::group([
         'prefix' => 'e-sim',
      ], function () {

         Route::group([
            'prefix' => 'countries',
         ], function () {
            Route::get('', [GigaEsimController::class, 'get_countries']);
            Route::get('{country_code}', [GigaEsimController::class, 'get_products']);
         });

         Route::group([
            'prefix' => 'products',
         ], function () {
            Route::get('{product_id}', [GigaEsimController::class, 'get_product_details']);
         });

         Route::group([
            'prefix' => 'orders',
         ], function () {
            Route::get('', [GigaEsimController::class, 'get_orders']);
            Route::get('{order_id}', [GigaEsimController::class, 'get_order']);
            Route::post('{product_id}', [GigaEsimController::class, 'create_order']);
            Route::delete('{order_id}/refund', [GigaEsimController::class, 'refund_order']);
         });

         Route::get('balance', [GigaEsimController::class, 'view_balance']);
         

      });

   });

   Route::group([
      'prefix' => 'reporting',
   ], function () {
      Route::group([
         'prefix' => 'sales-report',
         'middleware' => 'high-priority',
      ], function () {
         Route::get('{token_id}/{date_from}/{date_to}', [SalesReportController::class, 'get_sales_report']);
      });
   });
});
