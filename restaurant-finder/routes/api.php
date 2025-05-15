<?php

use App\Http\Controllers\RestaurantController;
use Illuminate\Support\Facades\Route;

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

// Restaurant API routes
Route::get('/restaurants', [RestaurantController::class, 'index']);
Route::get('/restaurants/nearby', [RestaurantController::class, 'nearby']);
Route::get('/restaurants/cuisines', [RestaurantController::class, 'cuisines']);
Route::get('/geocode', [RestaurantController::class, 'geocode']);
Route::get('/restaurants/{id}', [RestaurantController::class, 'show'])->where('id', '[0-9]+|[A-Za-z0-9_-]+');
Route::get('/test', [RestaurantController::class, 'test']);
