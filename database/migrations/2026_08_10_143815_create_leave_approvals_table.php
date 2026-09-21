<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aprobador_id')->constrained('users')->cascadeOnDelete();
            $table->integer('orden'); // 1, 2, 3... nivel de aprobación
            $table->enum('accion', ['pendiente', 'aprobado', 'rechazado'])->default('pendiente');
            $table->text('comentario')->nullable();
            $table->string('credential_id')->nullable(); // passkey credential usado
            $table->boolean('fallback_password')->default(false); // si usó password en vez de passkey
            $table->timestamp('fecha_accion')->nullable();
            $table->timestamps();
            $table->unique(['leave_request_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_approvals');
    }
};
