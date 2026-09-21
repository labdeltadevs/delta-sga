<?php

namespace App\Models;

use Database\Factories\VacationConfigFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $gestion
 * @property int $dias_por_ley
 * @property Carbon|null $fecha_limite_toma
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VacationConfig extends Model
{
    /** @use HasFactory<VacationConfigFactory> */
    use HasFactory;

    protected $fillable = [
        'gestion', 'dias_por_ley', 'fecha_limite_toma', 'activo',
    ];

    protected $casts = [
        'fecha_limite_toma' => 'date',
        'activo' => 'boolean',
    ];

    /**
     * @param  Builder<VacationConfig>  $query
     * @return Builder<VacationConfig>
     */
    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public static function getForGestion(int $gestion): ?self
    {
        return self::activo()->where('gestion', $gestion)->first();
    }

    public static function getCurrent(): ?self
    {
        return self::activo()->where('gestion', now()->year)->first();
    }
}
