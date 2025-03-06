<?php

namespace App\Helpers;

use App\Models\Device;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Helper
{
    public static function updateCoordinates($address): array
    {

        $result = [];

        if ($address) {
            $response = Http::withHeaders([
                'User-Agent' => 'LaravelApp/1.0 (' . env('MAPBOX_API_EMAIL') . ')',
            ])->get('https://api.mapbox.com/search/geocode/v6/forward', [
                'q' => $address,
                'proximity' => 'ip',
                'limit' => 1,
                'access_token' => env('MAPBOX_API_KEY'),
            ]);

            if ($response->successful()) {

                $data = $response->json();
                $result = [];

                if (!empty($data) && isset($data['features'][0])) {
                    $feature = $data['features'][0]; // Prendi la prima feature

                    // Estrarre le coordinate (latitudine e longitudine)
                    if (isset($feature['geometry']['coordinates'])) {
                        $coordinates = $feature['geometry']['coordinates'];
                        $result['latitude'] = $coordinates[1] ?? null; // Latitudine
                        $result['longitude'] = $coordinates[0] ?? null; // Longitudine
                    }

                    // Estrarre il contesto
                    if (isset($feature['properties']['context'])) {
                        $context = $feature['properties']['context'];

                        // Estrarre il codice paese
                        $result['country_code'] = $context['country']['country_code'] ?? null;

                        // Estrarre il nome della città
                        $result['city'] = $context['place']['name'] ?? null;

                        // Estrarre il nome dello stato o regione
                        $result['state'] = $context['region']['name'] ?? null;

                        // Estrarre il codice postale
                        $result['postal_code'] = $context['postcode']['name'] ?? null;

                        // Estrarre il nome della strada
                        $result['street'] = $context['address']['street_name'] ?? null;

                        // Estrarre il numero civico
                        $result['number'] = $context['address']['address_number'] ?? null;
                    }

                    // Estrarre il fuso orario (se disponibile)
                    $result['timezone'] = $feature['properties']['timezone'] ?? null;
                }

// Log dei dati estratti per verifica (opzionale)
                Log::info($result);

// Il risultato finale conterrà i dati estratti
                return $result;

            }

        }
        return $result;
    }


    public static function getDevice($device_uuid, $user_id): Device
    {
        $device = Device::firstOrCreate([
            'device_uuid' => $device_uuid,
            'user_id' => $user_id,
        ], [
            'device_name' => $request->device_name ?? 'Unknown',
        ]);

        if (!$device) {
            throw new \Exception('Device not authorized.');
        }

        return $device;
    }
}