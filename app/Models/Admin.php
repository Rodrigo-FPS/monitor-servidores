<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    protected $table = 'admins';

    protected $fillable = ['username', 'password'];

    protected $hidden = ['password', 'remember_token']; //nunca exponer credenciales en respuestas JSON
}
