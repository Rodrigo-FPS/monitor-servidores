<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servidores', function (Blueprint $table) {
            $table->string('server_id', 64)->primary(); //solo alfanumericos guion y guion bajo
            $table->string('hostname', 255);
            $table->string('ip_registrada', 45); //IPv4 o IPv6 unica IP autorizada para este servidor
            $table->text('secreto'); //secreto HMAC de 64 hex chars mostrar solo al registrar
            $table->string('estado', 20)->default('indeterminado');
            $table->dateTime('ultimo_visto')->nullable();
            $table->string('ultimo_tipo_mensaje', 20)->nullable();
            $table->dateTime('registrado_en')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servidores');
    }
};
