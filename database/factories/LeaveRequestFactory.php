<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $fechaDesde = Carbon::parse(fake()->dateTimeBetween('-30 days', '+30 days'));
        $fechaHasta = $fechaDesde->copy()->addDays(fake()->numberBetween(0, 5));

        $tipo = fake()->randomElement(['permiso', 'licencia', 'vacacion']);
        $diasTotales = (int) $fechaDesde->diffInDays($fechaHasta) + 1;

        return [
            'user_id' => User::factory(),
            'tipo' => $tipo,
            'motivo' => fake()->sentence(),
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'hora_desde' => $tipo !== 'vacacion' ? fake()->time('H:i') : null,
            'hora_hasta' => $tipo !== 'vacacion' ? fake()->time('H:i') : null,
            'jornada' => fake()->randomElement(['completa', 'media']),
            'considerado_como' => fake()->randomElement(['vacacion', 'descuento_planilla', 'sin_afectar']),
            'comentarios' => fake()->optional()->paragraph(),
            'estado' => fake()->randomElement(['borrador', 'pendiente', 'aprobado', 'rechazado']),
            'dias_totales' => $diasTotales,
            'dias_habiles' => $diasTotales,
            'anios_servicio' => $tipo === 'vacacion' ? fake()->numberBetween(1, 20) : null,
            'dias_correspondientes' => $tipo === 'vacacion' ? fake()->numberBetween(15, 300) : null,
            'fecha_limite_vacaciones' => $tipo === 'vacacion' ? Carbon::create(now()->year + 1, 3, 31) : null,
            'firma_bpm_trabajador' => null,
            'autorizado_por' => null,
            'fecha_aprobacion' => null,
            'recibido_por' => null,
            'fecha_recepcion' => null,
        ];
    }

    public function pendiente(): static
    {
        return $this->state(fn () => ['estado' => 'pendiente']);
    }

    public function aprobado(): static
    {
        return $this->state(fn () => [
            'estado' => 'aprobado',
            'autorizado_por' => User::factory(),
            'fecha_aprobacion' => now(),
        ]);
    }

    public function rechazado(): static
    {
        return $this->state(fn () => ['estado' => 'rechazado']);
    }

    public function vacacion(): static
    {
        return $this->state(fn () => [
            'tipo' => 'vacacion',
            'considerado_como' => 'vacacion',
            'anios_servicio' => fake()->numberBetween(1, 20),
            'dias_correspondientes' => fake()->numberBetween(15, 300),
            'fecha_limite_vacaciones' => Carbon::create(now()->year + 1, 3, 31),
        ]);
    }
}
