<?php

namespace App\Billing;

use App\Support\Quarter;
use Carbon\CarbonInterface;

/**
 * The Therapie Aktiv billing rules.
 *
 * - Billing happens per calendar quarter.
 * - The quarter the patient was enrolled in is programme quarter 1.
 * - The counter keeps running every calendar quarter until the patient is deregistered.
 * - Every fourth programme quarter is billed as 97a (yearly) instead of 97.
 */
final class BillingRules
{
    /**
     * Which programme quarter $quarter is for a patient enrolled on $enrolledAt (1-based), or null before enrolment.
     */
    public static function programmeQuarter(CarbonInterface $enrolledAt, Quarter $quarter): ?int
    {
        $elapsed = $quarter->quartersSince(Quarter::fromDate($enrolledAt));

        return $elapsed < 0 ? null : $elapsed + 1;
    }

    public static function codeFor(int $programmeQuarter): BillingCode
    {
        return $programmeQuarter % 4 === 0 ? BillingCode::Annual : BillingCode::Regular;
    }
}
