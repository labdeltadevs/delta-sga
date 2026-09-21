<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class HolidayService
{
    /**
     * @var Collection<int, Carbon>
     */
    protected Collection $cache;

    public function __construct()
    {
        $this->cache = collect();
    }

    /**
     * Determina si una fecha es día hábil (no fin de semana ni feriado activo).
     */
    public function esDiaHabil(CarbonInterface $fecha): bool
    {
        if ($fecha->isWeekend()) {
            return false;
        }

        return Holiday::esDiaHabil($fecha);
    }

    /**
     * Cuenta los días hábiles entre dos fechas (inclusive o exclusivo).
     */
    public function contarDiasHabiles(CarbonInterface $desde, CarbonInterface $hasta, bool $inclusive = true): int
    {
        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->startOfDay();

        if ($hasta->lt($desde)) {
            return 0;
        }

        if (! $inclusive) {
            $hasta = $hasta->subDay();
        }

        $dias = 0;
        $fecha = $desde->copy();

        while ($fecha->lte($hasta)) {
            if ($this->esDiaHabil($fecha)) {
                $dias++;
            }
            $fecha = $fecha->addDay();
        }

        return $dias;
    }

    /**
     * Suma N días hábiles a partir de una fecha.
     */
    public function sumarDiasHabiles(CarbonInterface $desde, int $dias): CarbonInterface
    {
        $fecha = $desde->copy();
        $agregados = 0;

        while ($agregados < $dias) {
            $fecha = $fecha->addDay();
            if ($this->esDiaHabil($fecha)) {
                $agregados++;
            }
        }

        return $fecha;
    }

    /**
     * Devuelve la lista de fechas hábiles entre dos fechas.
     *
     * @return Collection<int, CarbonInterface>
     */
    public function fechasHabiles(CarbonInterface $desde, CarbonInterface $hasta, bool $inclusive = true): Collection
    {
        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->startOfDay();

        if (! $inclusive) {
            $hasta = $hasta->subDay();
        }

        $fechas = collect();
        $fecha = $desde->copy();

        while ($fecha->lte($hasta)) {
            if ($this->esDiaHabil($fecha)) {
                $fechas->push($fecha->copy());
            }
            $fecha = $fecha->addDay();
        }

        return $fechas;
    }
}
