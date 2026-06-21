<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntentoLoginFallido extends Model
{
    protected $table = 'intentos_login_fallidos';

    public $timestamps = false;

    protected $fillable = ['ip', 'username', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
