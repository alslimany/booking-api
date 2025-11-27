<?php

use App\Core\Videcom;
use App\Http\Controllers\Api\FlightSearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('fix-prices', function () {

});
require __DIR__ . '/api/v1.php';
require __DIR__ . '/api/v2.php';
require __DIR__ . '/api/v3.php';
require __DIR__ . '/api/v4.php'; // For Airline Companies
require __DIR__ . '/api/v5.php'; // For Mobile Application and External Apis
