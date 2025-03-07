<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GeolocationService
{
    private int $distanceTolerance;

    public function __construct(int $distanceTolerance = 150)
    {
        $this->distanceTolerance = $distanceTolerance;
    }

    protected function calculateDistance(mixed $latitude, mixed $longitude, $location): float
    {
        $earthRadius = 6371000;
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

    /**
     * @throws \Exception
     */
    public function validateDistance($latitude, $longitude, $location): bool
    {

        // Calcoliamo la distanza
        $distance = self::calculateDistance(
            $latitude,
            $longitude,
            $location
        );

        if ($distance > $this->distanceTolerance) {
            throw new \Exception('Distance from location is greater than tolerance, distance: ' . $distance . ' meters');
        }

        return true;
    }
}