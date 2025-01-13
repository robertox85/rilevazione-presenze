<?php

use App\Http\Controllers\API\UserAuthController;
use App\Http\Controllers\TemporaryAuthController;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/tokens/create', function (Request $request) {
    $token = $request->user()->createToken($request->token_name);

    return ['token' => $token->plainTextToken];
});

Route::post('register-device', [UserAuthController::class, 'registerDevice'])->middleware('auth:sanctum');
Route::get('/temp-login', [TemporaryAuthController::class, 'handleLogin'])
    ->name('temp.login')
    ->middleware('signed');

Route::get('/device-registration-complete', function (Request $request) {
    // Qui puoi gestire il ritorno all'app dopo la registrazione del device
    return response()->json([
        'message' => 'Device registration completed',
        'status' => 'success'
    ]);
})->name('device.registration.complete');

Route::post('login', [UserAuthController::class, 'login']);
Route::post('logout', [UserAuthController::class, 'logout'])->middleware('auth:sanctum');

Route::post('/reset', function (Request $request) {

    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    DB::table('devices')->truncate();
    DB::table('attendances')->truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');


    return response()->json(['message' => 'Presenze e dispositivi resettati con successo.'], 200);
});



/**
 * Record presence
 *
 * This endpoint allows you to record the presence of a user in a specific location.
 *
 * URL: /api/check-in
 *
 * @queryParam latitude required Latitude. Example: 45.464664
 * @queryParam longitude required Longitude. Example: 9.190782
 * @queryParam user_id required User ID. Example: 1
 *
 * @response {
 * "message": "Presenza registrata con successo.",
 * "distance": 10.5
 * }
 *
 * @response 400 {
 * "error": "Parametri mancanti."
 * }
 *
 * @response 404 {
 * "error": "Utente non trovato."
 * }
 */
Route::post('/check-in', [UserAuthController::class, 'checkIn'])->middleware('auth:sanctum');


// Record check-out
Route::post('/check-out', [UserAuthController::class, 'checkOut'])->middleware('auth:sanctum');


// for testing only. Reset all presenze
Route::get('/reset-presenze', function (Request $request) {
    Attendance::truncate();
    return response()->json(['message' => 'Presenze resettate con
successo.'], 200);
})->middleware('auth:sanctum');

// for listing users
Route::get('/users', function (Request $request) {
    return User::all();
})->middleware('auth:sanctum');