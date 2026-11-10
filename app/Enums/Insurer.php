<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Insurer: string implements HasLabel
{
    case Oegk = 'oegk';
    case Svs = 'svs';
    case Bvaeb = 'bvaeb';
    case Kfa = 'kfa';

    public function getLabel(): string
    {
        return match ($this) {
            self::Oegk => 'ÖGK',
            self::Svs => 'SVS',
            self::Bvaeb => 'BVAEB',
            self::Kfa => 'KFA',
        };
    }
}
