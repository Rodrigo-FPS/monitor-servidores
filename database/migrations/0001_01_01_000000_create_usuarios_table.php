<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64)->unique();
            $table->string('password', 255);                 //hash bcrypt cost 12
            $table->string('rol', 20)->default('observador'); //minimo privilegio por defecto
            $table->rememberToken();
            $table->timestamps();
        });

        // Solo se permiten los dos roles validos (a nivel de base de datos).
        DB::statement(
            "ALTER TABLE usuarios ADD CONSTRAINT chk_usuarios_rol CHECK (rol IN ('admin','observador'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
