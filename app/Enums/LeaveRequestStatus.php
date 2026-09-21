<?php

namespace App\Enums;

enum LeaveRequestStatus: string
{
    case Borrador = 'borrador';
    case Pendiente = 'pendiente';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Pendiente => 'Pendiente',
            self::Aprobado => 'Aprobado',
            self::Rechazado => 'Rechazado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Aprobado => 'green',
            self::Rechazado => 'red',
            self::Pendiente => 'amber',
            self::Borrador, self::Cancelado => 'gray',
        };
    }
}
