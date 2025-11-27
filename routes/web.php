<?php

use App\Http\Controllers\CommandRequestController;
use App\Http\Controllers\FareRuleController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AeroTokenController;
use App\Http\Controllers\HotelTokenController;
use App\Http\Controllers\ApiLogController;
use App\Http\Controllers\FareRuleItemController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {

    return response()->json([
        'status' => 'OK',
        'message' => 'Running',
        'timestamp' => date('Y-m-d H:i:s'),
        'timezone' => env("APP_TIMEZONE"),
    ]);


});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        $aero_tokens = \App\Models\AeroToken::all();

        $prices = [];
        foreach ($aero_tokens as $aero_token) {
            if (array_key_exists(($aero_token->data['currency_code']), $prices)) {
                $prices[$aero_token->data['currency_code']] += $aero_token->data['balance'];
            } else {
                $prices[$aero_token->data['currency_code']] = $aero_token->data['balance'];
            } 
        }
        return inertia('Dashboard', [
            'aero_tokens' => $aero_tokens,
            'prices' => $prices,
        ]);
    })->name('dashboard');

    Route::resource('aero-tokens', AeroTokenController::class);
    Route::resource('hotel-tokens', HotelTokenController::class);
    Route::resource('api-logs', ApiLogController::class);
    Route::resource('command-requests', CommandRequestController::class);
    Route::resource('fare-rules', FareRuleController::class);
    Route::group([
        'prefix' => 'fare-rules/items',
        'as' => 'fare-rules.items.',
    ], function () {
        Route::post('store', [FareRuleItemController::class, 'store'])->name('store');
    });

    Route::get('flight-schedules/{iata}', [\App\Http\Controllers\FlightScheduleController::class, 'index']);
});
