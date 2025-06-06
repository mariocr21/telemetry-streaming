<?php

namespace App;

enum UserRole: string
{
    case SUPER_ADMIN = 'SA';
    case CLIENT_ADMIN = 'CA';
    case CLIENT_USER = 'CU';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrador',
            self::CLIENT_ADMIN => 'Administrador de Cliente',
            self::CLIENT_USER => 'Usuario de Cliente',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Acceso completo al sistema',
            self::CLIENT_ADMIN => 'Administrador del cliente con permisos extendidos',
            self::CLIENT_USER => 'Usuario básico del cliente',
        };
    }

    public static function default(): self
    {
        return self::CLIENT_USER;
    }

    public static function options(): array
    {
        return [
            self::SUPER_ADMIN->value => self::SUPER_ADMIN->label(),
            self::CLIENT_ADMIN->value => self::CLIENT_ADMIN->label(),
            self::CLIENT_USER->value => self::CLIENT_USER->label(),
        ];
    }
}
