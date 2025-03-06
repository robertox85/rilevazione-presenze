<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Models\Attendance;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AttendanceController
{
    private const DISTANCE_TOLERANCE = 150; // Tolleranza di 150 metri

    public function checkIn(Request $request): JsonResponse
    {
        try {
            $this->validateCheckInRequest($request);
            $device = Helper::getDevice($request->device_uuid, $request->user()->id);
            $user = $this->getUser($request);
            $location = $user->getLocation();
            $nowInLocationTimezone = $location->nowInLocationTimezone();

            $this->validateAttendanceCheckIn($user, $nowInLocationTimezone->format('Y-m-d'));
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

    public function checkOut(Request $request): JsonResponse
    {
        try {

            $this->validateCheckOutRequest($request);
            $user = $request->user();
            $location = $user->getLocation();
            $nowInLocationTimezone = $location->nowInLocationTimezone();
            $this->validateAttendanceCheckout($user, $nowInLocationTimezone->toDateString());
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

    private function validateAttendanceCheckout(User $user, $date): void
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
                'regex:/^[0-9a-fA-F]{16}$/'
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
                'regex:/^[0-9a-fA-F]{16}$/'
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


    protected function calculateDistance(mixed $latitude, mixed $longitude, $location): float
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

    private function validateAttendanceCheckIn(User $user, $date): void
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