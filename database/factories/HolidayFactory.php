<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        $feriadosBolivia = [
            ['Año Nuevo', '01-01', true, 'nacional'],
            ['Día del Estado Plurinacional', '01-22', true, 'nacional'],
            ['Carnaval', '02-12', true, 'nacional'],
            ['Carnaval', '02-13', true, 'nacional'],
            ['Viernes Santo', '03-29', true, 'nacional'],
            ['Día del Trabajo', '05-01', true, 'nacional'],
            ['Corpus Christi', '05-30', true, 'nacional'],
            ['Día de la Patria', '08-06', true, 'nacional'],
            ['Día de la Independencia', '08-07', true, 'nacional'],
            ['Todos los Santos', '11-02', true, 'nacional'],
            ['Navidad', '12-25', true, 'nacional'],
        ];

        $feriado = fake()->randomElement($feriadosBolivia);
        $anio = fake()->numberBetween(now()->year - 1, now()->year + 1);

        return [
            'nombre' => $feriado[0],
            'fecha' => Carbon::create($anio, (int) substr($feriado[1], 0, 2), (int) substr($feriado[1], 3, 2)),
            'tipo' => $feriado[3],
            'departamento' => null,
            'recurrente_anual' => $feriado[2],
            'activo' => true,
        ];
    }

    public function nacional(): static
    {
        return $this->state(fn () => ['tipo' => 'nacional']);
    }

    public function departamental(string $departamento): static
    {
        return $this->state(fn () => ['tipo' => 'departamental', 'departamento' => $departamento]);
    }

    public function suspension(): static
    {
        return $this->state(fn () => ['tipo' => 'suspension']);
    }
}
