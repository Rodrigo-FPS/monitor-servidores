<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Columna de rol de aplicacion. Por defecto 'usuario' (minimo privilegio):
        // una cuenta creada sin fijar rol explicitamente NO puede modificar nada.
        Schema::table('admins', function (Blueprint $table) {
            $table->string('rol', 20)->default('usuario')->after('password');
        });

        // Las cuentas YA existentes eran administradores: se promueven a 'admin'
        // para no dejarlas de solo lectura tras la migracion.
        DB::table('admins')->update(['rol' => 'admin']);

        // Restriccion a nivel BD: solo se permiten los dos roles validos.
        DB::statement(
            "ALTER TABLE admins ADD CONSTRAINT chk_admins_rol CHECK (rol IN ('admin','usuario'))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE admins DROP CONSTRAINT IF EXISTS chk_admins_rol');

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('rol');
        });
    }
};
