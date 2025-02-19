<?php

namespace App\Models;

use App\Helpers\Helper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
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
                $data = Helper::updateCoordinates($location->address);
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

    public function isWorkingDay(Carbon $date): bool
    {
        $working_days = $this->working_days ?? [1, 2, 3, 4, 5]; // Default: lunedì-venerdì

        // Controlla se il giorno della settimana è un giorno lavorativo
        if (!in_array($date->dayOfWeekIso, $working_days)) {
            return false;
        }

        // Controlla se la sede esclude le festività
        if ($this->exclude_holidays) {
            $holiday = Holiday::where('location_id', $this->id)
                ->where('holiday_date', $date->toDateString())
                ->exists();

            if ($holiday) {
                return false;
            }
        }

        return true;
    }
}
