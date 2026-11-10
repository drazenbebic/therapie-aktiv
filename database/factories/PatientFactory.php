<?php

namespace Database\Factories;

use App\Enums\DeregistrationReason;
use App\Enums\Insurer;
use App\Models\Patient;
use App\Support\SvNumber;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dateOfBirth = CarbonImmutable::instance(fake()->dateTimeBetween('-90 years', '-35 years'));

        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'sv_number' => self::svNumberFor($dateOfBirth),
            'date_of_birth' => $dateOfBirth,
            'insurer' => fake()->randomElement([Insurer::Oegk, Insurer::Oegk, Insurer::Oegk, Insurer::Svs, Insurer::Bvaeb, Insurer::Kfa]),
            'height_cm' => fake()->numberBetween(150, 195),
            'weight_kg' => fake()->randomFloat(1, 55, 130),
            'notes' => null,
            'enrolled_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-4 years', 'now')),
            'deregistered_at' => null,
            'deregistration_reason' => null,
        ];
    }

    public function enrolledOn(string|DateTimeInterface $date): static
    {
        return $this->state(fn (): array => ['enrolled_at' => CarbonImmutable::parse($date)]);
    }

    public function deregisteredOn(string|DateTimeInterface $date, DeregistrationReason $reason = DeregistrationReason::Deregistered): static
    {
        return $this->state(fn (): array => [
            'deregistered_at' => CarbonImmutable::parse($date),
            'deregistration_reason' => $reason,
        ]);
    }

    /**
     * Builds a valid, unique SV number whose date part matches the date of birth.
     */
    public static function svNumberFor(DateTimeInterface $dateOfBirth): string
    {
        $datePart = $dateOfBirth->format('dmy');

        do {
            $serial = (string) fake()->numberBetween(100, 999);
            $check = SvNumber::checkDigit($serial, $datePart);
            $number = $serial.$check.$datePart;
        } while ($check === null || Patient::query()->where('sv_number', $number)->exists());

        return $number;
    }
}
