<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Location extends Model
{
    protected $table = 'locations';
    protected $fillable =
        [
            'name',
            'address',
            'street',
            'number',
            'city',
            'state',
            'postal_code',
            'country_code',
            'latitude',
            'longitude',
            'timezone',
            'working_start_time',
            'working_end_time',
            'working_days',
            'exclude_holidays',
            'active'
        ];
    protected $casts = [
        'working_start_time' => 'datetime:H:i',
        'working_end_time' => 'datetime:H:i',
        'working_days' => 'array',
        'active' => 'boolean',
        'exclude_holidays' => 'boolean'
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Una sede ha molte festività
    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function (Location $location) {
            if ($location->isDirty('address')) {
                // $data = $this->updateCoordinates($location->address);
                $data = $location->updateCoordinates($location->address);

                if (!empty($data)) {
                    $location->latitude = $data['latitude'];
                    $location->longitude = $data['longitude'];
                    $location->country_code = $data['country_code'];
                    $location->city = $data['city'];
                    $location->state = $data['state'];
                    $location->postal_code = $data['postal_code'];
                    $location->street = $data['street'];
                    $location->number = $data['number'];
                }
            }
        });

        static::created(function (Location $location) {
            Log::info("Location {$location->name} created");

            // refresh the model to get the updated values
            $location->refresh();
        });
    }

    private function updateCoordinates($address): array
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


                // Il risultato finale conterrà i dati estratti
                return $result;

            }

        }

        return $result;
    }

    public function getTimezone(): string
    {
        return $this->timezone ?? 'UTC';
    }

    public function isWorkingDay(): bool
    {
        $date = $this->nowInLocationTimezone();
        $working_days = $this->working_days ?? [1, 2, 3, 4, 5]; // Default: lunedì-venerdì

        $today = $date->dayOfWeekIso;
        $isWorkingDay = in_array($today, $working_days);
        $isHoliday = $this->isHoliday($date);

        if (!$isWorkingDay) {
            return false;
        }

        if ($isHoliday && $this->exclude_holidays) {
            return false;
        }

        return true;
    }

    private function isHoliday(Carbon $date): bool
    {
        return $this->holidays()->where('holiday_date', $date->format('Y-m-d'))->exists();
    }

    public function nowInLocationTimezone(): Carbon
    {
        return Carbon::now($this->getTimezone());
    }

    public function getWorkingStartTime(): string
    {
        return $this->working_start_time->format('H:i');
    }

    public function getWorkingEndTime(): string
    {
        return $this->working_end_time->format('H:i');
    }
}
