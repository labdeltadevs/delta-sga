<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VacationBalance;
use App\Models\VacationConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VacationBalance>
 */
class VacationBalanceFactory extends Factory
{
    protected $model = VacationBalance::class;

    public function definition(): array
    {
        $aniosServicio = fake()->numberBetween(1, 20);
        $diasTotales = $aniosServicio * 15;

        return [
            'user_id' => User::factory(),
            'gestion' => fake()->numberBetween(now()->year - 1, now()->year),
            'anios_servicio' => $aniosServicio,
            'dias_totales' => $diasTotales,
            'dias_tomados' => fake()->numberBetween(0, $diasTotales),
            'dias_pendientes' => $diasTotales,
            'fecha_limite_toma' => VacationConfig::getForGestion(fake()->numberBetween(now()->year - 1, now()->year))?->fecha_limite_toma,
        ];
    }

    public function paraUsuario(User $user, int $gestion): static
    {
        return $this->state(fn () => ['user_id' => $user->id, 'gestion' => $gestion]);
    }
}
