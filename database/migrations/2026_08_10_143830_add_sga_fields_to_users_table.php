<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nombre1')->nullable()->after('name');
            $table->string('nombre2')->nullable()->after('nombre1');
            $table->string('apellido1')->nullable()->after('nombre2');
            $table->string('apellido2')->nullable()->after('apellido1');
            $table->enum('genero', ['M', 'F'])->nullable()->after('apellido2');
            $table->date('fecha_nacimiento')->nullable()->after('genero');
            $table->date('fecha_contrato')->nullable()->after('fecha_nacimiento');
            $table->foreignId('area_id')->nullable()->after('fecha_contrato')->constrained()->nullOnDelete();
            $table->foreignId('superior_id')->nullable()->after('area_id')->constrained('users')->nullOnDelete();
            $table->text('firma_bpm')->nullable()->after('superior_id');
            $table->string('avatar')->nullable()->after('firma_bpm');
            $table->boolean('es_superadmin')->default(false)->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
            $table->dropConstrainedForeignId('superior_id');
            $table->dropColumn([
                'nombre1', 'nombre2', 'apellido1', 'apellido2',
                'genero', 'fecha_nacimiento', 'fecha_contrato',
                'firma_bpm', 'avatar', 'es_superadmin',
            ]);
        });
    }
};
