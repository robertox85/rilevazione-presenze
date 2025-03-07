<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceValidationService
{
    /**
     * Minuti di margine per le verifiche delle finestre temporali proibite
     */
    private const TIME_MARGIN_MINUTES = 15;

    /**
     * Verifica se l'orario corrente cade in una finestra temporale proibita per il check-out
     * @throws \Exception
     */
    public function validateCheckOutTimeSlot(Location $location): void
    {
        $nowInLocationTimezone = $location->nowInLocationTimezone();
        $currentTimeOnly = $nowInLocationTimezone->format('H:i:s');

        $workStartTimeStr = $location->getWorkingStartTime();
        $workStartTime = $this->parseTimeString($workStartTimeStr, $location->getTimezone());
        $cutoffTime = $this->calculateCutoffTime($workStartTime, self::TIME_MARGIN_MINUTES, true);

        $isInForbiddenTimeSlot = $this->isInTimeRange($currentTimeOnly, $workStartTime, $cutoffTime);

        $this->logTimeSlotCheck('check-out', $isInForbiddenTimeSlot);

        if ($isInForbiddenTimeSlot) {
            throw new \Exception('Check-out not allowed within ' . self::TIME_MARGIN_MINUTES . ' minutes after work start time.');
        }
    }

    /**
     * Verifica se l'orario corrente cade in una finestra temporale proibita per il check-in
     * @throws \Exception
     */
    public function validateLocationCheckIn(Location $location): void
    {
        $nowInLocationTimezone = $location->nowInLocationTimezone();
        $currentTimeOnly = $nowInLocationTimezone->format('H:i:s');

        $workEndTimeStr = $location->getWorkingEndTime();
        $workEndTime = $this->parseTimeString($workEndTimeStr, $location->getTimezone());
        $cutoffTime = $this->calculateCutoffTime($workEndTime, self::TIME_MARGIN_MINUTES, false);

        $isInForbiddenTimeSlot = $this->isInTimeRange($currentTimeOnly, $cutoffTime, $workEndTime);

        $this->logTimeSlotCheck('check-in', $isInForbiddenTimeSlot);

        if ($isInForbiddenTimeSlot) {
            throw new \Exception('Check-in not allowed ' . self::TIME_MARGIN_MINUTES . ' minutes before work end time.');
        }
    }

    /**
     * Verifica se l'utente ha già registrato un check-in oggi
     * @throws \Exception
     */
    public function validateAttendanceCheckIn(User $user): void
    {
        $date = $this->getTodayDateForUser($user);
        $attendance = $this->findActiveAttendance($user->id, $date);

        if ($attendance) {
            throw new \Exception('Check-in already registered for today. You must check-out first.');
        }
    }

    /**
     * Verifica se l'utente ha registrato un check-in (ma non ancora un check-out) oggi
     * @throws \Exception
     */
    public function validateAttendanceCheckout(User $user): void
    {
        $date = $this->getTodayDateForUser($user);
        $attendance = $this->findActiveAttendance($user->id, $date);

        if (!$attendance) {
            throw new \Exception('Check-in not found for today. You must check-in first.');
        }
    }

    /**
     * Ottiene la data di oggi nel fuso orario dell'utente
     */
    private function getTodayDateForUser(User $user): string
    {
        $location = $user->getLocation();
        $nowInLocationTimezone = $location->nowInLocationTimezone();
        return $nowInLocationTimezone->format('Y-m-d');
    }

    /**
     * Trova una presenza attiva (con check-in ma senza check-out) per un utente in una data specifica
     */
    private function findActiveAttendance(int $userId, string $date)
    {
        return Attendance::where('user_id', $userId)
            ->whereDate('date', $date)
            ->whereNull('check_out')
            ->first();
    }

    /**
     * Analizza una stringa di orario e la converte in un oggetto Carbon
     */
    private function parseTimeString(string $timeStr, string $timezone): string
    {
        return Carbon::parse($timeStr, $timezone)->format('H:i:s');
    }

    /**
     * Calcola l'orario limite aggiungendo o sottraendo minuti
     */
    private function calculateCutoffTime(string $baseTime, int $minutes, bool $addMinutes): string
    {
        $carbon = Carbon::parse($baseTime);

        if ($addMinutes) {
            $carbon->addMinutes($minutes);
        } else {
            $carbon->subMinutes($minutes);
        }

        return $carbon->startOfMinute()->format('H:i:s');
    }

    /**
     * Verifica se un orario è compreso in un intervallo
     */
    private function isInTimeRange(string $time, string $startTime, string $endTime): bool
    {
        return $time >= $startTime && $time < $endTime;
    }

    /**
     * Registra nel log lo stato della verifica della finestra temporale
     */
    private function logTimeSlotCheck(string $operation, bool $isInForbiddenTimeSlot): void
    {
        Log::info("{$operation} in forbidden time slot: " . ($isInForbiddenTimeSlot ? 'true' : 'false'));
    }
}