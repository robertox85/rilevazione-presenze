<?php

namespace App\Http\Controllers\API;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;

use App\Models\Device;
use App\Models\Attendance;
use App\Models\User;

use Illuminate\Http\Request;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// Method to handle user login
use Hashids\Hashids;
use Illuminate\Support\Facades\URL;


class UserAuthController extends Controller
{
    private const DISTANCE_TOLERANCE = 20; // Tolleranza di 20 metri

    // Method to handle user authentication and token generation
    public function generateToken(Request $request)
    {

        $request->validate([

            'email' => 'required|email',

            'password' => 'required',

        ]);


        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {

            throw ValidationException::withMessages([

                'email' => ['The provided credentials are incorrect.'],

            ]);

        }

        $token = $user->createToken('my-app-token')->plainTextToken;

        return response()->json(['token' => $token], 200);

    }

    // Method to handle user registration
    public function register(Request $request)
    {

        try {
            $request->validate([

                'name' => 'required|string',

                'email' => 'required|email|unique:users,email',

                'password' => 'required|string',

            ]);

            $data = [

                'name' => $request->name,

                'email' => $request->email,

                'password' => Hash::make($request->password),

            ];

            $user = User::create($data);

            // assign role dipendente if exists
            $user->assignRole('employee');

            //$token = $user->createToken('my-app-token')->plainTextToken;

            return response()->json(
                [
                    'message' => 'User created successfully.',
                    'user_id' => $user->id,
                    // 'token' => $token
                ], 201
            );

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Registration failed.' . $e->getMessage()], 500);
        }

    }


    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
                'device_uuid' => 'required|uuid',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Invalid Credentials'
                ], 401);
            }

            $device = Device::where('user_id', $user->id)
                ->where('device_uuid', $request->device_uuid)
                ->first();

            if (!$device) {
                $hashids = new Hashids(config('app.key'), 10);
                $hashedId = $hashids->encode($user->id);

                // Semplifichiamo l'URL rimuovendo il callback per ora
                $tempUrl = URL::temporarySignedRoute(
                    'temp.login',
                    now()->addMinutes(30),
                    [
                        'u' => $hashedId,
                        'device_uuid' => $request->device_uuid,
                    ]
                );

                return response()->json([
                    'message' => 'Device not authorized.',
                    'registration_url' => $tempUrl,
                    'expires_in' => 30, // minuti
                ], 403);
            }

            if (!$user->active) {
                return response()->json([
                    'message' => 'User is not active.'
                ], 403);
            }

            if ($user->devices->count() === 1 && $user->devices->first()->device_uuid !== $request->device_uuid) {
                return response()->json([
                    'message' => 'Only one device is allowed.'
                ], 403);
            }

            $token = $user->createToken($request->device_uuid)->plainTextToken;

            return response()->json([
                'message' => 'Login successful.',
                'token' => $token,
                'device_name' => $device->device_name,
            ], 200);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Login failed.' . $e->getMessage()], 500);
        }
    }

    // Method to handle user logout and token revocation

    public function logout(Request $request)

    {

        // Revoke all tokens...

        $request->user()->tokens()->delete();

        // // Revoke the current token

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'You have been successfully logged out.'], 200);

    }


    // Method to handle user check-in
    public function checkIn(Request $request)
    {
        try {
            // Validazione dei dati
            $this->validateCheckInRequest($request);

            // Recupera l'utente autenticato
            $user = $request->user();

            $device = Device::where('user_id', $user->id)
                ->where('device_uuid', $request->device_uuid)
                ->first();

            if (!$device) {
                return response()->json(['error' => 'Device not authorized.'], 403);
            }

            $location = $user->location;

            // Verifica il fuso orario e ottieni l'ora attuale nella sede
            $timezone = $location->timezone ?? 'UTC'; // Default a UTC se non configurato
            $nowInLocationTimezone = Carbon::now($timezone);

            // Verifica se oggi è un giorno lavorativo
            $this->validateWorkingDay($location, $nowInLocationTimezone);

            // Recupera gli orari lavorativi
            $this->validateWorkingHours($location, $nowInLocationTimezone, $timezone, $nowInLocationTimezone);

            $isExternal = $user->contract_type === 'external';
            if (!$isExternal) {
                $this->validateDistance($request->latitude, $request->longitude, $location);
            }

            // Registra la presenza
            $this->registerPresence($user, $device, $request, $nowInLocationTimezone);


            return response()->json([
                'message' => 'Check-in successfully registered.',
                'distance' => $this->calculateDistance($request->latitude, $request->longitude, $location),
                'device_uuid' => $request->device_uuid,
                'user_id' => $user->id,
                'date' => now()->format('Y-m-d'),
                'check_in' => now()->format('H:i:s'),
                'timezone' => $location->timezone,
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // Method to handle user check-out
    public function checkOut(Request $request)
    {
        try {

            // Validazione dei dati
            $this->validateCheckOutRequest($request);

            $device = Device::where('user_id', $request->user_id)
                ->where('device_uuid', $request->device_uuid)
                ->first();

            if (!$device) {
                return response()->json(['error' => 'Dispositivo non autorizzato.'], 403);
            }

            // Recupera l'utente e la sede associata
            $user = $this->getUserWithLocation($request->user_id);
            $location = $user->location;
            $timezone = $location->timezone ?? 'UTC'; // Default a UTC se non configurato

            // Trova la presenza di oggi per l'utente
            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('date', now()->toDateString())
                ->first();

            if (!$attendance) {
                return response()->json(['error' => 'Attendance not found.'], 404);
            }

            if ($attendance->check_out) {
                return response()->json(['error' => 'Check-out already registered.'], 400);
            }

            // Confronta orario di uscita con orario lavorativo della sede
            $orarioCheckOut = Carbon::now($timezone);

            if ($orarioCheckOut->lt(Carbon::parse($attendance->check_in))) {
                return response()->json(['error' => 'Check-out time is before check-in time.'], 400);
            }

            // Controllo opzionale su coordinate di uscita (ad esempio distanza massima dalla sede)
            $isExternal = $user->contract_type === 'external';
            if (!$isExternal) {
                $this->validateDistance($request->latitude, $request->longitude, $location);
            }

            // Registra l'uscita
            $attendance->check_out = $orarioCheckOut->format('H:i:s');
            $attendance->check_out_latitude = $request->latitude;
            $attendance->check_out_longitude = $request->longitude;
            $attendance->save();

            return response()->json([
                'message' => 'Check-out successfully registered.',
                'date' => now()->format('Y-m-d'),
                'check_in' => $attendance->check_in,
                'check_out' => $attendance->check_out,
                'user' => $user->name . ' ' . $user->surname,
            ]);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    protected function validateCheckInRequest(Request $request): void
    {
        $rules = [
            'latitude' => [
                'required',
                'numeric',
                'min:-90',
                'max:90',
                'regex:/^-?\d{1,2}\.\d+$/',
            ],
            'longitude' => [
                'required',
                'numeric',
                'min:-180',
                'max:180',
                'regex:/^-?\d{1,3}\.\d+$/',
            ],

            'check_in' => [
                'required',
                'date_format:H:i:s',
            ],
            'device_uuid' => 'required|uuid',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->messages()->all());
        }
    }

    protected function validateCheckOutRequest(Request $request): void
    {
        $rules = [
            'latitude' => [
                'required',
                'numeric',
                'min:-90',
                'max:90',
                'regex:/^-?\d{1,2}\.\d+$/',
            ],
            'longitude' => [
                'required',
                'numeric',
                'min:-180',
                'max:180',
                'regex:/^-?\d{1,3}\.\d+$/',
            ],
            'user_id' => [
                'required',
                'exists:users,id',
                'integer',
            ],
            // 'device_uuid' => [
            //     'required',
            //     'uuid',
            //     'exists:devices,device_uuid',
            // ],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->messages()->all());
        }
    }

    protected function getUserWithLocation(int $userId): User
    {
        $user = User::with('location')->find($userId);

        if (!$user || !$user->location) {
            throw new \Exception('User not found or missing related data.');
        }

        return $user;
    }

    protected function validateWorkingDay($location, $nowInLocationTimezone): void
    {
        if (!$location->working_days) {
            throw new \Exception('Working days not configured.');
        }

        if (!$location->isWorkingDay($nowInLocationTimezone)) {
            throw new \Exception('Not a working day.');
        }
    }

    protected function validateWorkingHours($location, string $checkIn, string $timezone, Carbon $nowInLocationTimezone): void
    {
        // Normalizza l'orario di check-in e rimuove i secondi
        $checkInUser = Carbon::parse($checkIn, $timezone)->startOfMinute();

        // Definisce i limiti dell'orario lavorativo con tolleranza (senza secondi)
        $workingStartTime = Carbon::parse($location->working_start_time, $timezone)->subMinutes(15)->startOfMinute();
        $workingEndTime = Carbon::parse($location->working_end_time, $timezone)->addMinutes(15)->startOfMinute();

        // Log per debug (facoltativo)
        Log::info("Check-in: $checkInUser, Start: $workingStartTime, End: $workingEndTime, Now: {$nowInLocationTimezone->startOfMinute()}");

        // Controlla che il check-in non sia nel passato
        if ($checkInUser->lt($nowInLocationTimezone->startOfMinute())) {
            throw new \Exception('Check-in time cannot be in the past.');
        }

        // Controlla che il check-in sia all'interno dell'orario lavorativo
        if ($checkInUser->lt($workingStartTime) || $checkInUser->gt($workingEndTime)) {
            throw new \Exception('Check-in time is outside working hours.');
        }
    }

    protected function registerPresence(User $user, Device $device, Request $request, Carbon $nowInLocationTimezone)
    {
        // Registra la presenza
        Attendance::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'date' => $nowInLocationTimezone->toDateString(),
            'check_in' => $nowInLocationTimezone->format('H:i:s'),
            'check_in_latitude' => $request->latitude,
            'check_in_longitude' => $request->longitude,
        ]);
    }

    public function registerDevice(Request $request)
    {
        $request->validate([
            'device_name' => 'required|string|max:255',
            'device_uuid' => 'required|uuid',
            'user_id' => 'required|exists:users,id',
        ]);

        $device = Device::create([
            'device_name' => $request->device_name,
            'device_uuid' => $request->device_uuid,
            'user_id' => $request->user_id,
        ]);

        return response()->json(['message' => 'Device registered successfully.', 'device' => $device], 201);
    }

    public function validateDistance($latitude, $longitude, $location): bool
    {

        // Calcoliamo la distanza
        $distance = self::calculateDistance(
            $latitude,
            $longitude,
            $location
        );


        if ($distance > self::DISTANCE_TOLERANCE) {
            throw new \Exception('Distance from location is greater than tolerance, distance: ' . $distance . ' meters');
        }

        return true;
    }

    protected function calculateDistance(mixed $latitude, mixed $longitude, $location)
    {
        $earthRadius = 6371000; // Raggio terrestre in metri

        $latFrom = deg2rad($latitude);
        $lonFrom = deg2rad($longitude);
        $latTo = deg2rad($location->latitude);
        $lonTo = deg2rad($location->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos($latFrom) * cos($latTo) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a)); // Calcola l'angolo tra due punti

        $mt = $earthRadius * $c; // Distanza in metri


        // Calcola se la distanza è superiore a 1000 metri, restituendo i chilometri
        if ($mt > 1000) {
            return round($mt / 1000, 2);
        }

        // Round to 2 decimal places
        return round($mt, 2);
    }

}
