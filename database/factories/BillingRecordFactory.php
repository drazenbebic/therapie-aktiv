<?php

namespace Database\Factories;

use App\Billing\BillingCode;
use App\Models\BillingRecord;
use App\Models\Patient;
use App\Support\Quarter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingRecord>
 */
class BillingRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quarter = Quarter::current();

        return [
            'patient_id' => Patient::factory(),
            'year' => $quarter->year,
            'quarter' => $quarter->number,
            'programme_quarter' => 1,
            'code' => BillingCode::Regular,
            'billed_at' => now(),
        ];
    }

    public function forQuarter(Quarter $quarter): static
    {
        return $this->state(fn (): array => ['year' => $quarter->year, 'quarter' => $quarter->number]);
    }
}
