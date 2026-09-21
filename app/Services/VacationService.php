<?php

namespace App\Services;

use App\Models\User;
use App\Models\VacationBalance;
use App\Models\VacationConfig;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class VacationService
{
    public function __construct(
        protected HolidayService $holidays,
    ) {}

    /**
     * Años de servicio completos del usuario a una fecha dada.
     */
    public function aniosServicio(User $user, ?Carbon $fecha = null): int
    {
        $fecha = $fecha ?? now();

        if (! $user->fecha_contrato) {
            return 0;
        }

        return (int) $user->fecha_contrato->diffInYears($fecha);
    }

    /**
     * Días de vacaciones que le corresponden al usuario para una gestión.
     * Cada año de servicio cumplido = dias_por_ley (15) días.
     */
    public function diasCorrespondientes(User $user, int $gestion): int
    {
        // La gestión de vacaciones corresponde al año calendario en curso.
        $anios = $this->aniosServicio($user, Carbon::create($gestion, 12, 31));

        $config = VacationConfig::getForGestion($gestion);

        return $anios * ($config->dias_por_ley ?? 15);
    }

    /**
     * Recupera o crea el saldo de vacaciones del usuario para una gestión.
     */
    public function balance(User $user, int $gestion): VacationBalance
    {
        $config = VacationConfig::getForGestion($gestion);

        return VacationBalance::updateOrCreate(
            ['user_id' => $user->id, 'gestion' => $gestion],
            [
                'anios_servicio' => $this->aniosServicio($user, Carbon::create($gestion, 12, 31)),
                'dias_totales' => $this->diasCorrespondientes($user, $gestion),
                'fecha_limite_toma' => $config?->fecha_limite_toma,
            ]
        );
    }

    /**
     * Días hábiles efectivos de un rango de fechas (para una solicitud de vacaciones).
     */
    public function diasHabilesParaRango(Carbon $desde, Carbon $hasta): int
    {
        return $this->holidays->contarDiasHabiles($desde, $hasta);
    }

    /**
     * Valida que el usuario tenga saldo suficiente para tomar N días.
     */
    public function saldoSuficiente(User $user, int $gestion, int $diasSolicitados): bool
    {
        $balance = $this->balance($user, $gestion);

        return $balance->dias_pendientes >= $diasSolicitados;
    }

    /**
     * Registra el consumo de días de vacaciones aprobados.
     */
    public function registrarConsumo(User $user, int $gestion, int $dias): void
    {
        $balance = $this->balance($user, $gestion);

        $balance->dias_tomados += $dias;
        $balance->recalcular();
    }

    /**
     * Refleja el balance en fechas límite: los días tomados se descuentan
     * automáticamente del total según el saldo pendiente.
     */
    public function fechaLimitePara(User $user, int $gestion): ?CarbonInterface
    {
        return VacationConfig::getForGestion($gestion)?->fecha_limite_toma;
    }
}
