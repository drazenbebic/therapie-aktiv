<?php

namespace App\Billing;

use App\Models\BillingRecord;
use App\Models\Patient;

/**
 * One row of the quarterly billing list.
 */
final readonly class BillingLine
{
    public function __construct(
        public Patient $patient,
        public int $programmeQuarter,
        public BillingCode $code,
        public ?BillingRecord $record = null,
    ) {}

    public function isBilled(): bool
    {
        return $this->record !== null;
    }
}
