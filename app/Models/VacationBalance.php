<?php

namespace App\Models;

use Database\Factories\VacationBalanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $gestion
 * @property int $anios_servicio
 * @property int $dias_totales
 * @property int $dias_tomados
 * @property int $dias_pendientes
 * @property Carbon|null $fecha_limite_toma
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read VacationConfig|null $config
 */
class VacationBalance extends Model
{
    /** @use HasFactory<VacationBalanceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'gestion', 'anios_servicio', 'dias_totales', 'dias_tomados', 'dias_pendientes', 'fecha_limite_toma',
    ];

    protected $casts = [
        'fecha_limite_toma' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<VacationConfig, $this> */
    public function config(): BelongsTo
    {
        return $this->belongsTo(VacationConfig::class, 'gestion', 'gestion');
    }

    public function recalcular(): void
    {
        $this->dias_pendientes = $this->dias_totales - $this->dias_tomados;
        $this->save();
    }

    public static function crearOActualizarParaUsuario(User $user, int $gestion): self
    {
        $config = VacationConfig::getForGestion($gestion);
        $aniosServicio = $user->anios_servicio;
        $diasTotales = $aniosServicio * ($config->dias_por_ley ?? 15);
        $fechaLimite = $config?->fecha_limite_toma;

        $balance = self::updateOrCreate(
            ['user_id' => $user->id, 'gestion' => $gestion],
            [
                'anios_servicio' => $aniosServicio,
                'dias_totales' => $diasTotales,
                'fecha_limite_toma' => $fechaLimite,
            ]
        );

        $balance->recalcular();

        return $balance;
    }
}
