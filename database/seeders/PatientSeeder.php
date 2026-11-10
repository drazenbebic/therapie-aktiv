<?php

namespace Database\Seeders;

use App\Billing\QuarterlyBilling;
use App\Enums\DeregistrationReason;
use App\Models\Patient;
use App\Support\Quarter;
use Illuminate\Database\Seeder;

/**
 * Demo data: fake patients with realistic enrolment dates, a few deregistrations,
 * and the past quarters already marked as billed.
 */
class PatientSeeder extends Seeder
{
    public function run(QuarterlyBilling $billing): void
    {
        Patient::factory()->count(48)->create();

        Patient::factory()->count(3)->enrolledOn(now()->subYears(2))->deregisteredOn(now()->subMonths(5))->create();
        Patient::factory()->enrolledOn(now()->subYears(3))->deregisteredOn(now()->subMonths(1), DeregistrationReason::Deceased)->create();

        $current = Quarter::current();
        $quarter = Quarter::fromDate(now()->subYears(4));

        while ($quarter != $current) {
            $billing->markAsBilled($quarter, $quarter->end()->addDays(5)->setTime(10, 0));
            $quarter = $quarter->next();
        }
    }
}
