<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('codigo_empleado')->unique()->nullable()->after('avatar');
            $table->string('carnet')->unique()->nullable()->after('codigo_empleado');
            $table->string('ocupacion_original')->nullable()->after('carnet');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['codigo_empleado', 'carnet', 'ocupacion_original']);
        });
    }
};
