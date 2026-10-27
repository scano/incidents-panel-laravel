<?php

namespace App\Enums;

enum IncidentPriority: string
{
    case Low = 'baja';
    case Medium = 'media';
    case High = 'alta';
    case Critical = 'critica';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Baja',
            self::Medium => 'Media',
            self::High => 'Alta',
            self::Critical => 'Crítica',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Low => '↓',
            self::Medium => '＝',
            self::High => '↑',
            self::Critical => '↑↑',
        };
    }
}
