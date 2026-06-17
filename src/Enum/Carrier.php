<?php

namespace App\Enum;

enum Carrier: string
{
    case GLS = 'gls';
    case CZ_POSTA = 'cz_posta';
    case SK_POSTA = 'posta_sk';

    public function label(): string
    {
        return match ($this) {
            self::GLS => 'GLS',
            self::CZ_POSTA => 'Balíkovna',
            self::SK_POSTA => 'Slovenská pošta',
        };
    }
}
