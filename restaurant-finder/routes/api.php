<?php
// routes/api.php

use App\Http\Controllers\RestaurantController;
use Illuminate\Support\Facades\Route;

// Using Laravel 12's improved route grouping
Route::prefix('restaurants')->group(function () {
    Route::get('/', [RestaurantController::class, 'index']);
    Route::get('/{id}', [RestaurantController::class, 'show']);
});