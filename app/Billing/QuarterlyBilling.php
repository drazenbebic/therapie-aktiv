<?php

namespace App\Billing;

use App\Enums\Insurer;
use App\Models\BillingRecord;
use App\Models\Patient;
use App\Support\Quarter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the billing list for a calendar quarter and records it as billed.
 *
 * The list is computed from the enrolment dates on every request; only the act of billing is stored.
 */
final class QuarterlyBilling
{
    /**
     * All patients that can be billed for the quarter, sorted by insurer and name.
     *
     * Lines that were already billed carry their record, and show the code as it was billed.
     *
     * @return Collection<int, BillingLine>
     */
    public function linesFor(Quarter $quarter): Collection
    {
        $patients = Patient::query()
            ->activeIn($quarter)
            ->with(['billingRecords' => fn ($query) => $query->where('year', $quarter->year)->where('quarter', $quarter->number)])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $insurerOrder = array_flip(array_map(fn (Insurer $insurer): string => $insurer->value, Insurer::cases()));

        return $patients
            ->map(function (Patient $patient) use ($quarter): BillingLine {
                $record = $patient->billingRecords->first();
                $programmeQuarter = $record->programme_quarter ?? $patient->programmeQuarterIn($quarter);

                return new BillingLine(
                    patient: $patient,
                    programmeQuarter: $programmeQuarter,
                    code: $record->code ?? BillingRules::codeFor($programmeQuarter),
                    record: $record,
                );
            })
            ->sortBy(fn (BillingLine $line): int => $insurerOrder[$line->patient->insurer->value], SORT_NUMERIC)
            ->values();
    }

    /**
     * @return Collection<string, Collection<int, BillingLine>> Keyed by insurer label, in insurer order.
     */
    public function linesByInsurer(Quarter $quarter): Collection
    {
        return $this->linesFor($quarter)->groupBy(fn (BillingLine $line): string => $line->patient->insurer->getLabel());
    }

    /**
     * Stores a billing record for every line of the quarter that isn't billed yet.
     *
     * @return int Number of newly billed patients.
     */
    public function markAsBilled(Quarter $quarter, ?CarbonImmutable $billedAt = null): int
    {
        $billedAt ??= CarbonImmutable::now();

        return DB::transaction(function () use ($quarter, $billedAt): int {
            $unbilled = $this->linesFor($quarter)->reject(fn (BillingLine $line): bool => $line->isBilled());

            foreach ($unbilled as $line) {
                BillingRecord::query()->create([
                    'patient_id' => $line->patient->id,
                    'year' => $quarter->year,
                    'quarter' => $quarter->number,
                    'programme_quarter' => $line->programmeQuarter,
                    'code' => $line->code,
                    'billed_at' => $billedAt,
                ]);
            }

            return $unbilled->count();
        });
    }

    /**
     * Removes all billing records of the quarter, e.g. after marking it as billed by mistake.
     *
     * @return int Number of removed records.
     */
    public function undoBilling(Quarter $quarter): int
    {
        return BillingRecord::query()
            ->where('year', $quarter->year)
            ->where('quarter', $quarter->number)
            ->delete();
    }
}
