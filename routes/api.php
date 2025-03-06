<?php

use App\Http\Controllers\API\UserAuthController;
use App\Http\Controllers\TemporaryAuthController;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\User;
use App\Services\AttendanceController;
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


Route::post('login', [UserAuthController::class, 'login']);
Route::post('logout', [UserAuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('status', [UserAuthController::class, 'status'])->middleware('auth:sanctum');

Route::post('/check-in', [AttendanceController::class, 'checkIn'])->middleware('auth:sanctum');
Route::post('/check-out', [AttendanceController::class, 'checkOut'])->middleware('auth:sanctum');


Route::get('/users', function (Request $request) {
    return User::all();
})->middleware('auth:sanctum');


Route::post('/reset', function (Request $request) {

    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    DB::table('devices')->truncate();
    DB::table('attendances')->truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');


    return response()->json(['message' => 'Presenze e dispositivi resettati con successo.'], 200);
});

Route::get('/reset-presenze', function (Request $request) {
    Attendance::truncate();
    return response()->json(['message' => 'Presenze resettate con
successo.'], 200);
})->middleware('auth:sanctum');