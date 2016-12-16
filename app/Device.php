<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'device_id',
        'os_enum',
        'model',
        'operator',
        'api_level',
        'push_service_enum',
        'push_service_id'
    ];

    /* RELATIONS */

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
