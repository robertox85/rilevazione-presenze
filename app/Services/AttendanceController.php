<?php

namespace App\Services;

use App\Models\Attendance;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;

class AttendanceController
{

    private GeolocationService $geoService;
    private WorkingTimeService $timeService;
    private RequestValidationService $requestValidator;
    private AttendanceValidationService $attendanceValidator;

    public function __construct(
        GeolocationService          $geoService,
        WorkingTimeService          $timeService,
        RequestValidationService    $requestValidator,
        AttendanceValidationService $attendanceValidator,
    )
    {
        $this->geoService = $geoService;
        $this->timeService = $timeService;
        $this->requestValidator = $requestValidator;
        $this->attendanceValidator = $attendanceValidator;
    }

    public function checkIn(Request $request): JsonResponse
    {
        try {
            $this->requestValidator->validateCheckInRequest($request);

            $user = $request->user();
            $deviceUuid = $request->input('device_uuid');
            $deviceName = $request->input('device_name');
            $device = $user->getDevice($deviceUuid, $deviceName);
            $location = $user->getLocation();

            $nowInLocationTimezone = $location->nowInLocationTimezone();
            $today = $nowInLocationTimezone->format('Y-m-d');
            $check_in_time_request = $nowInLocationTimezone->format('H:i:s');

            $this->attendanceValidator->validateAttendanceCheckIn($user);
            $this->attendanceValidator->validateLocationCheckIn($location);
            $this->timeService->validateLocationWorkingHours($location);
            $this->timeService->validateLocationWorkingDays($location);

            if (!$user->isExternal()) {
                $this->geoService->validateDistance($request->latitude, $request->longitude, $location);
            }

            Attendance::create([
                'user_id' => $user->id ?? null,
                'device_id' => $device->id ?? null,
                'date' => $today,
                'check_in' => $check_in_time_request,
                'check_in_latitude' => $request->latitude,
                'check_in_longitude' => $request->longitude,
            ]);

            return response()->json([
                'message' => 'Check-in successfully registered.',
                'user_id' => $user->id ?? null,
                'date' => $today,
                'check_in' => $check_in_time_request,
            ], 201);

        } catch (\Exception $e) {
            $stack = $e->getTrace();
            Log::error($e->getMessage() . ' ' . $stack[0]['file'] . ' ' . $stack[0]['line']);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function checkOut(Request $request): JsonResponse
    {
        try {

            $user = $request->user();
            $location = $user->getLocation();
            $nowInLocationTimezone = $location->nowInLocationTimezone();
            $today = $nowInLocationTimezone->format('Y-m-d');
            $check_out_time_request = $nowInLocationTimezone->format('H:i:s');

            $this->requestValidator->validateCheckOutRequest($request);
            $this->attendanceValidator->validateAttendanceCheckout($user);
            $this->attendanceValidator->validateCheckOutTimeSlot($location);


            if (!$user->isExternal()) {
                $this->geoService->validateDistance($request->latitude, $request->longitude, $location);
            }

            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('date', $today)
                ->whereNull('check_out')
                ->first();

            $attendance->update([
                'check_out' => $check_out_time_request,
                'check_out_latitude' => $request->latitude,
                'check_out_longitude' => $request->longitude,
            ]);

            return response()->json([
                'message' => 'Check-out successfully registered.',
                'user_id' => $user->id,
                'date' => $today,
                'check_out' => $check_out_time_request,
            ], 200);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


}