<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequestNotification>
 */
class LeaveRequestNotificationFactory extends Factory
{
    protected $model = LeaveRequestNotification::class;

    public function definition(): array
    {
        return [
            'leave_request_id' => LeaveRequest::factory(),
            'user_id' => User::factory(),
            'tipo' => fake()->randomElement(['nueva_solicitud', 'aprobada', 'rechazada', 'sla_warning', 'vencimiento_vacaciones', 'aprobacion_requerida']),
            'mensaje' => fake()->sentence(),
            'leida' => fake()->boolean(20),
            'leida_at' => null,
        ];
    }

    public function leida(): static
    {
        return $this->state(fn () => ['leida' => true, 'leida_at' => now()]);
    }

    public function noLeida(): static
    {
        return $this->state(fn () => ['leida' => false, 'leida_at' => null]);
    }
}
