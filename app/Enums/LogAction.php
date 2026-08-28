<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Eventos Eloquent capturados pelos observers e persistidos em system_logs.
 */
enum LogAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Criação',
            self::Updated => 'Alteração',
            self::Deleted => 'Exclusão',
            self::Restored => 'Restauração',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Created => 'bg-secondary-100 text-secondary-800',
            self::Updated => 'bg-accent-100 text-accent-800',
            self::Deleted => 'bg-danger-100 text-danger-800',
            self::Restored => 'bg-primary-100 text-primary-800',
        };
    }
}
