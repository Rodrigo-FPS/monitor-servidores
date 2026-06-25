<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    protected $table = 'usuarios';

    // 'rol' NO es asignable en masa: evita escalado de privilegios si alguna ruta
    // llegara a crear/actualizar cuentas con entrada del usuario. Se fija siempre
    // de forma explicita ($usuario->rol = 'admin').
    protected $fillable = ['username', 'password'];

    protected $hidden = ['password', 'remember_token']; //nunca exponer credenciales en respuestas JSON

    // Valor por defecto de una cuenta nueva: minimo privilegio (solo lectura).
    protected $attributes = ['rol' => 'observador'];

    /**
     * Mutador de seguridad: cualquier valor distinto de los roles validos cae a
     * 'observador' (fail-safe). No afecta a la carga desde BD, solo a la asignacion.
     */
    public function setRolAttribute($value): void
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';
        $this->attributes['rol'] = in_array($value, ['admin', 'observador'], true) ? $value : 'observador';
    }

    public function esAdmin(): bool
    {
        return $this->rol === 'admin';
    }
}
