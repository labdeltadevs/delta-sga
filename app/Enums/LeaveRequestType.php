<?php

namespace App\Enums;

enum LeaveRequestType: string
{
    case Permiso = 'permiso';
    case Licencia = 'licencia';
    case Vacacion = 'vacacion';

    public function label(): string
    {
        return match ($this) {
            self::Permiso => 'Permiso',
            self::Licencia => 'Licencia',
            self::Vacacion => 'Vacación',
        };
    }
}
