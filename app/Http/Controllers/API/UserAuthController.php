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
    private const DISTANCE_TOLERANCE = 150; // Tolleranza di 20 metri
    private const REGEX_UUID = 'regex:/^[0-9a-fA-F]{16}$/';

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
            $user->assignRole('Employee');

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

    protected function getTempUrl($device_uuid, $userId)
    {
        $hashids = new Hashids(config('app.key'), 10);
        $hashedId = $hashids->encode($userId);

        // Semplifichiamo l'URL rimuovendo il callback per ora
        $tempUrl = URL::temporarySignedRoute(
            'temp.login',
            now()->addMinutes(30),
            [
                'u' => $hashedId,
                'device_uuid' => $device_uuid,
            ]
        );

        return $tempUrl;
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
                // optional device uuid
                'device_uuid' => [
                    'nullable',
                     self::REGEX_UUID
                ],
                'device_name' => 'nullable|string|max:255',
            ]);

            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json([
                    'message' => 'User not found.'
                ], 401);
            }

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Invalid Credentials.'
                ], 401);
            }

            $device = null;
            if($request->device_uuid !== null && $request->device_uuid !== ''){
                $device = Device::where('user_id', $user->id)
                    ->where('device_uuid', $request->device_uuid)
                    ->first();

                if (!$device) {
                    // $tempUrl = $this->getTempUrl($request->device_uuid, $user->id);
                    // return response()->json([
                    //     'message' => 'Device not authorized. Please register the device.',
                    //     'registration_url' => $tempUrl,
                    //     'expires_in' => 30, // minuti
                    // ], 403);

                    // Register the device automatically
                    $device = Device::create([
                        'device_name' => $request->device_name ?? 'Unknown',
                        'device_uuid' => $request->device_uuid,
                        'user_id' => $user->id,
                    ]);
                }
            }


            if (!$user->active) {
                return response()->json([
                    'message' => 'User is not active.'
                ], 403);
            }

            // if ($user->devices->count() === 1 && $user->devices->first()->device_uuid !== $request->device_uuid) {
            //     return response()->json([
            //         'message' => 'Only one device is allowed.'
            //     ], 403);
            // }

            $token = $user->createToken('my-app-token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful.',
                'token' => $token,
                'user' => $user,
                'device' => ($device) ? $device : null,
                'location' => $user->location,
            ], 200);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Login failed.' . $e->getMessage()], 500);
        }
    }


    public function status( Request $request )
    {
        $user = $request->user();
        $location = $user->location;
        $timezone = $location->timezone ?? 'UTC';
        $nowInLocationTimezone = Carbon::now($timezone)->startOfMinute();

        return response()->json([
            'user' => $user,
            'now' => $nowInLocationTimezone->toDateTimeString(),
        ], 200);
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

    public function checkOut(Request $request)
    {
        try {

           // Validazione richiesta
            $this->validateCheckOutRequest($request);

            // Recupera presenza di oggi per questo utente
            $user = $request->user();
            $isExternal = $user->contract_type === 'external';
            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('date', Carbon::now()->toDateString())
                ->first();

            if (!$attendance) {
                return response()->json(['message' => 'Check-in not found for today.'], 404);
            }

            if ($attendance->check_out) {
                return response()->json(['message' => 'Check-out already registered for today.'], 400);
            }

            // Converti l'orario di check-out in un oggetto Carbon UTC
            $checkOutTime = Carbon::createFromFormat('H:i:s', $request->check_out, 'UTC')
                ->startOfMinute();

            // verifica se sta uscendo prima dell'orario di check-in
            if ($checkOutTime->lt(Carbon::parse($attendance->check_in))) {
                throw new \Exception('Check-out time is earlier than check-in time.');
            }

            // Verifica se oggi è un giorno lavorativo
            $location = $user->location;
            $this->validateWorkingDay($location, Carbon::now());

            // Verifica l'orario di check-out
            $this->validateWorkingHours($location, $checkOutTime, 'UTC', Carbon::now());

            // Verifica la distanza
            if (!$isExternal) {
                $this->validateDistance($request->latitude, $request->longitude, $location);
            }

            // Registra il check-out
            $attendance->update([
                'check_out' => $checkOutTime->format('H:i:s'),
                'check_out_latitude' => $request->latitude,
                'check_out_longitude' => $request->longitude,
            ]);


            return response()->json([
                'message' => 'Check-out successfully registered.',
                'user_id' => $user->id,
                'date' => Carbon::now()->toDateString(),
                'check_out' => $checkOutTime->format('H:i:s'),
            ], 200);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


    // Method to handle user check-in
    public function checkIn(Request $request)
    {
        try {

            // validazione richiesta
            $this->validateCheckInRequest($request);

            // Recupera il device dell'utente e se non esiste lo crea
            $device = Device::firstOrCreate([
                'device_uuid' => $request->device_uuid,
                'user_id' => $request->user()->id,
            ], [
                'device_name' => $request->device_name ?? 'Unknown',
            ]);

            // Recupera l'utente con la posizione
            $user = $this->getUserWithLocation($request->user()->id);

            // Verifica se l'utente è esterno
            $isExternal = $user->contract_type === 'external';

            // Verifica se l'utente ha già effettuato il check-in per oggi
            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('date', Carbon::now()->toDateString())
                ->first();

            if ($attendance) {
                return response()->json(['message' => 'Check-in already registered for today.'], 400);
            }

            // Verifica se oggi è un giorno lavorativo
            $location = $user->location;
            $this->validateWorkingDay($location, Carbon::now());

            // Verifica l'orario di check-in
            $this->validateWorkingHours($location, $request->check_in, 'UTC', Carbon::now());

            // Verifica la distanza
            if (!$isExternal) {
                $this->validateDistance($request->latitude, $request->longitude, $location);
            }

            // Registra il check-in
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'device_id' => $device->id,
                'date' => Carbon::now()->toDateString(),
                'check_in' => $request->check_in,
                'check_in_latitude' => $request->latitude,
                'check_in_longitude' => $request->longitude,
            ]);

            return response()->json([
                'message' => 'Check-in successfully registered.',
                'user_id' => $user->id,
                'date' => Carbon::now()->toDateString(),
                'check_in' => $request->check_in,
            ], 201);


        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // Method to handle user check-out


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

            'device_uuid' => [
                'required',
                 self::REGEX_UUID
            ],

            'device_name' => 'nullable|string|max:255',
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
            'check_out' => [
                'required',
                'date_format:H:i:s',
            ],
            'device_uuid' => [
                'required',
                self::REGEX_UUID
            ],
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
            throw new \Exception('User not found or missing location.');
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
        $margin = 15; // Minuti di margine per check-in e check-out

        try {
            $timestamps = [
                'check_in' => Carbon::parse($checkIn)->startOfMinute(),
                'work_start' => Carbon::parse($location->working_start_time, $timezone)->subMinutes($margin)->startOfMinute(),
                'work_end' => Carbon::parse($location->working_end_time, $timezone)->addMinutes($margin)->startOfMinute(),
                'now' => $nowInLocationTimezone
            ];


            if ($timestamps['now']->lt($timestamps['work_start'])) {
                throw new \Exception('Too early to check-in.');
            }

            if ($timestamps['now']->gt($timestamps['work_end'])) {
                throw new \Exception('Too late to check-in.');
            }

            if ($timestamps['check_in']->lt($timestamps['work_start'])) {
                throw new \Exception('Check-in time is too early.');
            }

            if ($timestamps['check_in']->gt($timestamps['work_end'])) {
                throw new \Exception('Check-in time is too late.');
            }


        } catch (\Exception $e) {
            if (!$e instanceof ValidationException) {
                throw  ValidationException::withMessages(['check_in' => [$e->getMessage()]]);
            }
            throw $e;
        }
    }


    public function registerDevice(Request $request)
    {
        $request->validate([
            'device_name' => 'required|string|max:255',
            'device_uuid' => [
                'required',
                self::REGEX_UUID
            ],
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
