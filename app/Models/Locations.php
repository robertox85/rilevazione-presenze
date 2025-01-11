<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Locations extends Model
{
    protected $table = 'locations';
    protected $fillable =
        [
            'name',
            'address',
           'start_time',
            'end_time',
            'working_days',
            'exclude_holidays',
            'active'
        ];
    protected $casts = ['active' => 'boolean'];
    protected $hidden = ['created_at', 'updated_at','country_code','latitude','longitude','timezone'];
}
