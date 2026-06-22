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

    public function supported(): bool
    {
        return match ($this) {
            self::GLS => true,
            default => false,
        };
    }

    /**
     * @return array<string>
     */
    public function countries(): array
    {
        return match ($this) {
            self::GLS => ["AT", "BE", "BG", "CZ", "DE", "DK", "ES", "FI", "FR", "GR", "HU", "HR", "IT", "LU",
                "NL", "PL", "PT", "RO", "SK", "SI"],
            default => []
        };
    }
}
