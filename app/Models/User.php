<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use HasRoles;
    use HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'location_id',
        'name',
        'surname',
        'email',
        'password',
        'tax_code',
        'contract_type',
        'employee_id',
        'active',
        'privacy_accepted_at',
        'geolocation_consent',
    ];

    protected $casts = [
        'active' => 'boolean',
        'privacy_accepted_at' => 'datetime',
        'geolocation_consent' => 'boolean',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Un utente appartiene a una sola sede
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    // Un utente ha molti devices
    public function devices(): HasOne
    {
        return $this->hasOne(Device::class);
    }

    // Un utente ha molte presenze
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function isExternal(): bool
    {
        return $this->contract_type === 'EXTERNAL';
    }
}
