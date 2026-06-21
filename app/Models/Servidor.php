<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Servidor extends Model
{
    protected $table = 'servidores';

    public $incrementing = false; //server_id es string no autoincremental
    protected $keyType   = 'string';
    protected $primaryKey = 'server_id';
    public $timestamps   = false;

    protected $fillable = [
        'server_id',
        'hostname',
        'ip_registrada',
        'secreto',
        'estado',
        'ultimo_visto',
        'ultimo_tipo_mensaje',
        'registrado_en',
    ];

    protected $hidden = ['secreto']; //el secreto HMAC nunca se expone en respuestas

    protected $casts = [
        'ultimo_visto'  => 'datetime',
        'registrado_en' => 'datetime',
    ];
}
