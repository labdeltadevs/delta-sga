<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    protected $model = Area::class;

    public function definition(): array
    {
        $nombres = [
            'Administrativa', 'Contabilidad', 'RRHH', 'Tesorería', 'Servicios',
            'Logística y Almacenes', 'Comercial', 'Gerencias Regionales', 'Equipo de Ventas',
            'T.I.C.', 'Producción', 'Microbiología', 'Sólidos', 'Semisólidos',
            'Líquidos', 'Empaque', 'Investigación y Desarrollo',
        ];

        $tipo = fake()->randomElement([
            'administrativa', 'comercial', 'tic', 'produccion',
            'gerencia_regional', 'equipo_ventas', 'area_produccion',
        ]);

        return [
            'nombre' => fake()->unique()->randomElement($nombres),
            'codigo' => strtoupper(Str::random(4)),
            'parent_id' => null,
            'head_user_id' => null,
            'tipo' => $tipo,
            'orden' => fake()->numberBetween(0, 100),
            'activo' => true,
        ];
    }

    public function conPadre(Area $padre): static
    {
        return $this->state(fn () => ['parent_id' => $padre->id]);
    }

    public function tipo(string $tipo): static
    {
        return $this->state(fn () => ['tipo' => $tipo]);
    }

    public function conJefe(): static
    {
        return $this->state(function () {
            return ['head_user_id' => User::factory()];
        });
    }
}
