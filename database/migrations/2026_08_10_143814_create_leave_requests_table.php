<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('tipo', ['permiso', 'licencia', 'vacacion'])->default('permiso');
            $table->string('motivo')->nullable();
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->time('hora_desde')->nullable();
            $table->time('hora_hasta')->nullable();
            $table->enum('jornada', ['completa', 'media'])->default('completa');
            $table->enum('considerado_como', ['vacacion', 'descuento_planilla', 'sin_afectar'])->default('sin_afectar');
            $table->text('comentarios')->nullable();
            $table->enum('estado', ['borrador', 'pendiente', 'aprobado', 'rechazado', 'cancelado'])->default('borrador');
            $table->integer('dias_totales')->default(0);
            $table->integer('dias_habiles')->default(0);

            // Campos específicos de vacaciones
            $table->integer('anios_servicio')->nullable();
            $table->integer('dias_correspondientes')->nullable();
            $table->date('fecha_limite_vacaciones')->nullable();

            // Firma BPM del trabajador (se copia del perfil al crear)
            $table->text('firma_bpm_trabajador')->nullable();

            // Campos de aprobación final
            $table->foreignId('autorizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->foreignId('recibido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_recepcion')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
