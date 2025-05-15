<?php

use App\Http\Controllers\RestaurantController;
use Illuminate\Support\Facades\Route;

// Make sure there are no typos in the controller name or namespace
Route::get('/restaurants', [RestaurantController::class, 'index']);
Route::get('/restaurants/cuisines', [RestaurantController::class, 'cuisines']);
Route::get('/restaurants/{id}', [RestaurantController::class, 'show']);

// Add a test endpoint
Route::get('/test', function () {
    return response()->json(['status' => 'ok', 'message' => 'API is working']);
});