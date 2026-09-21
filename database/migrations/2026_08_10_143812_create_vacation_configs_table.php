<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacation_configs', function (Blueprint $table) {
            $table->id();
            $table->integer('gestion')->unique(); // año de gestión
            $table->integer('dias_por_ley')->default(15); // días de vacación por año cumplido
            $table->date('fecha_limite_toma')->nullable(); // fecha límite para tomar vacaciones
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_configs');
    }
};
