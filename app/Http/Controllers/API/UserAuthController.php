<?php

namespace App\Http\Controllers\API;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;

use App\Models\Device;
use App\Models\Attendance;
use App\Models\Location;
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
    private const DISTANCE_TOLERANCE = 150; // Tolleranza di 150 metri
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
            if ($request->device_uuid !== null && $request->device_uuid !== '') {
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


    public function status(Request $request)
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

    public function checkIn(Request $request)
    {
        try {

            $this->validateCheckInRequest($request);

            $device = $this->getDevice($request);
            $user = $this->getUser($request);
            $location = $user->getLocation();
            $nowInLocationTimezone = $location->nowInLocationTimezone();

            $this->validateAttendaceCheckIn($user, $nowInLocationTimezone->format('Y-m-d'));
            $this->validateLocationCheckIn($location);
            $this->validateLocationWorkingHours($location);
            $this->validateLocationWorkingDays($location);

            if (!$user->isExternal()) {
                $this->validateDistance($request->latitude, $request->longitude, $location);
            }

            Attendance::create([
                'user_id' => $user->id,
                'device_id' => $device->id,
                'date' => $nowInLocationTimezone->toDateString(),
                'check_in' => $nowInLocationTimezone->format('H:i:s'),
                'check_in_latitude' => $request->latitude,
                'check_in_longitude' => $request->longitude,
            ]);

            return response()->json([
                'message' => 'Check-in successfully registered.',
                'user_id' => $user->id,
                'date' => $nowInLocationTimezone->toDateString(),
                'check_in' => $nowInLocationTimezone->format('H:i:s'),
            ], 201);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function _checkOut(Request $request)
    {
        try {

            // Validazione richiesta
            $this->validateCheckOutRequest($request);

            // Recupera presenza di oggi per questo utente
            $user = $request->user();

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
            $this->validateLocationWorkingDays($location);

            // Verifica l'orario di check-out
            $this->validateLocationWorkingHours($location, $checkOutTime, 'UTC', Carbon::now());

            // Verifica la distanza
            if ($user->contract_type !== 'EXTERNAL') {
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

    private function validateAttendaceCheckout(User $user, $date)
    {
        // get attendance for today with check-in, and check-out is null
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->whereNull('check_out')
            ->first();

        if (!$attendance) {
            throw new \Exception('Check-in not found for today. You must check-in first.');
        }
    }

    public function checkOut(Request $request)
    {
        try {

            $this->validateCheckOutRequest($request);
            $user = $request->user();
            $location = $user->getLocation();
            $nowInLocationTimezone = $location->nowInLocationTimezone();
            $this->validateAttendaceCheckout($user, $nowInLocationTimezone->toDateString());
            $this->validateCheckOutTimeSlot($location);


            if (!$user->isExternal()) {
                $this->validateDistance($request->latitude, $request->longitude, $location);
            }

            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('date', $nowInLocationTimezone->toDateString())
                ->whereNull('check_out')
                ->first();

            $attendance->update([
                'check_out' => $nowInLocationTimezone->format('H:i:s'),
                'check_out_latitude' => $request->latitude,
                'check_out_longitude' => $request->longitude,
            ]);

            return response()->json([
                'message' => 'Check-out successfully registered.',
                'user_id' => $user->id,
                'date' => $nowInLocationTimezone->toDateString(),
                'check_out' => $nowInLocationTimezone->format('H:i:s'),
            ], 200);

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

    protected function getUser($request): User
    {
        $userId = $request->user()->id;
        $user = User::with('location')->find($userId);

        if (!$user || !$user->location) {
            throw new \Exception('User not found or missing location.');
        }

        return $user;
    }

    protected function validateLocationWorkingDays($location): void
    {
        if (!$location->working_days) {
            throw new \Exception('Working days not configured.');
        }

        if (!$location->isWorkingDay()) {
            throw new \Exception('Not a working day.');
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

    private function getDevice($request): Device
    {
        $device = Device::firstOrCreate([
            'device_uuid' => $request->device_uuid,
            'user_id' => $request->user()->id,
        ], [
            'device_name' => $request->device_name ?? 'Unknown',
        ]);

        if (!$device) {
            throw new \Exception('Device not authorized.');
        }

        return $device;
    }

    private function validateCheckOutTimeSlot(Location $location): void
    {
        // Ottieni l'orario di inizio lavoro per oggi
        $nowInLocationTimezone = $location->nowInLocationTimezone();
        $today = $nowInLocationTimezone->format('Y-m-d'); // Ottiene solo la data di oggi
        $workStartTime = Carbon::parse($today . ' ' . $location->getWorkingStartTime(), $location->getTimezone());
        $cutoffTime = (clone $workStartTime)->addMinutes(15)->startOfMinute();

        // Confronta i timestamp
        $isInForbiddenTimeSlot = $nowInLocationTimezone->timestamp >= $workStartTime->timestamp
            && $nowInLocationTimezone->timestamp < $cutoffTime->timestamp;

        Log::info('Is in forbidden time slot: ' . ($isInForbiddenTimeSlot ? 'true' : 'false'));

        if ($isInForbiddenTimeSlot) {
            throw new \Exception('Check-out not allowed within 15 minutes after work start time.');
        }
    }

    private function validateLocationCheckIn(Location $location): void
    {
        $nowInLocationTimezone = $location->nowInLocationTimezone();
        // Ottieni solo l'orario corrente (ore, minuti, secondi)
        $currentTimeOnly = $nowInLocationTimezone->format('H:i:s');

        // Ottieni l'orario di fine lavoro e il cutoff come stringhe di orario
        $workEndTimeStr = $location->getWorkingEndTime(); // Assumendo che sia già nel formato "HH:MM:SS"
        $workEndTime = Carbon::parse($workEndTimeStr, $location->getTimezone())->format('H:i:s');
        $cutoffTime = Carbon::parse($workEndTimeStr, $location->getTimezone())
            ->subMinutes(15)
            ->startOfMinute()
            ->format('H:i:s');

        // Confronta solo gli orari come stringhe
        $isInForbiddenTimeSlot = $currentTimeOnly >= $cutoffTime && $currentTimeOnly < $workEndTime;


        if ($isInForbiddenTimeSlot) {
            throw new \Exception('Check-in not allowed 15 minutes before work end time.');
        }
    }

    protected function validateLocationWorkingHours($location): void
    {
        $margin = 15; // Minuti di margine

        try {
            $nowInLocationTimezone = $location->nowInLocationTimezone();

            // Assicurati che gli oggetti abbiano lo stesso timezone
            $workStart = Carbon::parse($location->getWorkingStartTime(), $location->getTimezone())
                ->subMinutes($margin)
                ->startOfMinute();

            $workEnd = Carbon::parse($location->getWorkingEndTime(), $location->getTimezone())
                ->addMinutes($margin)
                ->startOfMinute();

            $nowTime = $nowInLocationTimezone->format('H:i:s');
            $workStartTime = $workStart->format('H:i:s');
            $workEndTime = $workEnd->format('H:i:s');


            if ($nowTime < $workStartTime) {
                throw new \Exception('Too early to check-in. ' . $nowTime . ' < ' . $workStartTime);
            }

            if ($nowTime > $workEndTime) {
                throw new \Exception('Too late to check-in. ' . $nowTime . ' > ' . $workEndTime);
            }

        } catch (\Exception $e) {
            if (!$e instanceof ValidationException) {
                throw ValidationException::withMessages(['check_in' => [$e->getMessage()]]);
            }
            throw $e;
        }
    }

    private function validateAttendaceCheckIn(User $user, $date): void
    {
        // get attendance for today with check-in, and check-out is null
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->whereNull('check_out')
            ->first();

        if ($attendance) {
            throw new \Exception('Check-in already registered for today. You must check-ou first.');
        }
    }

    public function validateDistance($latitude, $longitude, $location): bool
    {

        // Calcoliamo la distanza
        $distance = self::calculateDistance(
            $latitude,
            $longitude,
            $location
        );

        Log::log('info', 'Distance from location: ' . $distance . ' meters');

        if ($distance > self::DISTANCE_TOLERANCE) {
            throw new \Exception('Distance from location is greater than tolerance, distance: ' . $distance . ' meters');
        }

        return true;
    }


}
