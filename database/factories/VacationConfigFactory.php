<?php

namespace Database\Factories;

use App\Models\VacationConfig;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<VacationConfig>
 */
class VacationConfigFactory extends Factory
{
    protected $model = VacationConfig::class;

    public function definition(): array
    {
        $anio = fake()->numberBetween(now()->year - 2, now()->year + 1);

        return [
            'gestion' => $anio,
            'dias_por_ley' => 15,
            'fecha_limite_toma' => Carbon::create($anio + 1, 3, 31), // 31 de marzo del año siguiente
            'activo' => true,
        ];
    }

    public function paraGestion(int $gestion): static
    {
        return $this->state(fn () => ['gestion' => $gestion]);
    }
}
