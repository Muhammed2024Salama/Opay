<?php

use App\Http\Controllers\OpayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');


Route::post('/opay/pay', [OpayController::class, 'pay']);
Route::post('/opay/callback', [OpayController::class, 'callback'])
    ->name('opay.callback');
