<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property Carbon $fecha
 * @property string $tipo
 * @property string|null $departamento
 * @property bool $recurrente_anual
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    protected $fillable = [
        'nombre', 'fecha', 'tipo', 'departamento', 'recurrente_anual', 'activo',
    ];

    protected $casts = [
        'fecha' => 'date',
        'recurrente_anual' => 'boolean',
        'activo' => 'boolean',
    ];

    /**
     * @param  Builder<Holiday>  $query
     * @return Builder<Holiday>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * @param  Builder<Holiday>  $query
     * @return Builder<Holiday>
     */
    public function scopeParaFecha(Builder $query, CarbonInterface $fecha): Builder
    {
        return $query->activos()->where(function ($q) use ($fecha) {
            $q->where('fecha', $fecha)
                ->orWhere(function ($q2) use ($fecha) {
                    $q2->where('recurrente_anual', true)
                        ->whereMonth('fecha', $fecha->month)
                        ->whereDay('fecha', $fecha->day);
                });
        });
    }

    public static function esDiaHabil(CarbonInterface $fecha): bool
    {
        if ($fecha->isWeekend()) {
            return false;
        }

        return ! self::activos()->paraFecha($fecha)->exists();
    }
}
