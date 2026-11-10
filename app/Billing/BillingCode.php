<?php

namespace App\Billing;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Therapie Aktiv billing positions.
 */
enum BillingCode: string implements HasColor, HasLabel
{
    /** Quarterly care. */
    case Regular = '97';

    /** Yearly care, billed instead of 97 in every fourth programme quarter. */
    case Annual = '97a';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Regular => 'gray',
            self::Annual => 'success',
        };
    }
}
