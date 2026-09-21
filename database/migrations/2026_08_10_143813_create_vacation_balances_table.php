<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacation_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('gestion'); // año de gestión
            $table->integer('anios_servicio')->default(0); // años de servicio cumplidos
            $table->integer('dias_totales')->default(0); // 15 * años_servicio
            $table->integer('dias_tomados')->default(0);
            $table->integer('dias_pendientes')->default(0); // calculado
            $table->date('fecha_limite_toma')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'gestion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_balances');
    }
};
