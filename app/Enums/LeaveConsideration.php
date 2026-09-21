<?php

namespace App\Enums;

enum LeaveConsideration: string
{
    case Vacacion = 'vacacion';
    case DescuentoPlanilla = 'descuento_planilla';
    case SinAfectar = 'sin_afectar';

    public function label(): string
    {
        return match ($this) {
            self::Vacacion => 'A cuenta de vacación',
            self::DescuentoPlanilla => 'Descuento por planilla',
            self::SinAfectar => 'Sin afectar planilla ni vacación',
        };
    }
}
