<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WorkingTimeService
{
    /**
     * @throws \Exception
     */
    public function validateLocationWorkingDays($location): void
    {
        if (!$location->working_days) {
            throw new \Exception('Working days not configured.');
        }

        if (!$location->isWorkingDay()) {
            throw new \Exception('Not a working day.');
        }
    }

    /**
     * @throws ValidationException
     */
    public function validateLocationWorkingHours($location): void
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

}