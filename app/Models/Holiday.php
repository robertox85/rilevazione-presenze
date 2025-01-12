<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $table = 'holidays';
    protected $fillable = [
        'location_id',
        'holiday_date',
        'description',
    ];
    protected $casts = ['holiday_date' => 'date'];
    protected $hidden = ['created_at'];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
