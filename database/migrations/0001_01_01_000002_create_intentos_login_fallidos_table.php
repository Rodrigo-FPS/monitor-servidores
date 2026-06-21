<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intentos_login_fallidos', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45);
            $table->string('username', 64)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->index(['ip', 'created_at']); //indice para contar intentos por IP en ventana de tiempo
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intentos_login_fallidos');
    }
};
