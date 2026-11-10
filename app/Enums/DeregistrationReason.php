<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DeregistrationReason: string implements HasLabel
{
    case Deregistered = 'deregistered';
    case Deceased = 'deceased';

    public function getLabel(): string
    {
        return match ($this) {
            self::Deregistered => 'Abgemeldet',
            self::Deceased => 'Verstorben',
        };
    }
}
