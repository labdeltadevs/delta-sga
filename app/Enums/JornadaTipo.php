<?php

namespace App\Enums;

enum JornadaTipo: string
{
    case Completa = 'completa';
    case Media = 'media';

    public function label(): string
    {
        return match ($this) {
            self::Completa => 'Día completo',
            self::Media => 'Medio día',
        };
    }
}
