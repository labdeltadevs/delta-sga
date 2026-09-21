<?php

namespace Database\Factories;

use App\Models\LeaveApproval;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveApproval>
 */
class LeaveApprovalFactory extends Factory
{
    protected $model = LeaveApproval::class;

    public function definition(): array
    {
        return [
            'leave_request_id' => LeaveRequest::factory(),
            'aprobador_id' => User::factory(),
            'orden' => fake()->numberBetween(1, 3),
            'accion' => fake()->randomElement(['pendiente', 'aprobado', 'rechazado']),
            'comentario' => fake()->optional()->sentence(),
            'credential_id' => fake()->optional()->uuid(),
            'fallback_password' => fake()->boolean(10),
            'fecha_accion' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function pendiente(): static
    {
        return $this->state(fn () => ['accion' => 'pendiente', 'fecha_accion' => null]);
    }

    public function aprobado(): static
    {
        return $this->state(fn () => ['accion' => 'aprobado', 'fecha_accion' => now()]);
    }

    public function rechazado(): static
    {
        return $this->state(fn () => ['accion' => 'rechazado', 'fecha_accion' => now()]);
    }
}
