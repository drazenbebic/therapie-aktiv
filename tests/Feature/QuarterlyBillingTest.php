<?php

namespace Tests\Feature;

use App\Billing\BillingCode;
use App\Billing\BillingLine;
use App\Billing\QuarterlyBilling;
use App\Enums\DeregistrationReason;
use App\Enums\Insurer;
use App\Models\BillingRecord;
use App\Models\Patient;
use App\Support\Quarter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuarterlyBillingTest extends TestCase
{
    use RefreshDatabase;

    private QuarterlyBilling $billing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->billing = new QuarterlyBilling;
    }

    public function test_it_lists_patients_enrolled_in_or_before_the_quarter(): void
    {
        $enrolledBefore = Patient::factory()->enrolledOn('2025-06-10')->create();
        $enrolledDuring = Patient::factory()->enrolledOn('2026-03-29')->create();
        Patient::factory()->enrolledOn('2026-04-01')->create();

        $lines = $this->billing->linesFor(new Quarter(2026, 1));

        $this->assertEqualsCanonicalizing(
            [$enrolledBefore->id, $enrolledDuring->id],
            $lines->map(fn (BillingLine $line): int => $line->patient->id)->all(),
        );
    }

    public function test_it_assigns_97a_to_every_fourth_programme_quarter(): void
    {
        $patient = Patient::factory()->enrolledOn('2026-02-15')->create();

        $codes = collect([
            new Quarter(2026, 1), new Quarter(2026, 2), new Quarter(2026, 3), new Quarter(2026, 4),
            new Quarter(2027, 1), new Quarter(2027, 4),
        ])->map(fn (Quarter $quarter): string => $this->billing->linesFor($quarter)->sole()->code->value);

        $this->assertSame(['97', '97', '97', '97a', '97', '97a'], $codes->all());
        $this->assertSame(4, $this->billing->linesFor(new Quarter(2026, 4))->sole()->programmeQuarter);
        $this->assertSame($patient->id, $this->billing->linesFor(new Quarter(2026, 4))->sole()->patient->id);
    }

    public function test_a_patient_deregistered_during_the_quarter_is_still_billed_for_it(): void
    {
        Patient::factory()->enrolledOn('2025-01-10')->deregisteredOn('2026-05-20', DeregistrationReason::Deceased)->create();

        $this->assertCount(1, $this->billing->linesFor(new Quarter(2026, 2)));
        $this->assertCount(0, $this->billing->linesFor(new Quarter(2026, 3)));
    }

    public function test_a_patient_deregistered_on_the_first_day_of_a_quarter_is_billed_for_that_quarter(): void
    {
        Patient::factory()->enrolledOn('2025-01-10')->deregisteredOn('2026-07-01')->create();

        $this->assertCount(1, $this->billing->linesFor(new Quarter(2026, 3)));
    }

    public function test_lines_are_grouped_by_insurer_in_fixed_order_and_sorted_by_name(): void
    {
        Patient::factory()->enrolledOn('2026-01-05')->create(['last_name' => 'Zach', 'insurer' => Insurer::Oegk]);
        Patient::factory()->enrolledOn('2026-01-05')->create(['last_name' => 'Huber', 'insurer' => Insurer::Kfa]);
        Patient::factory()->enrolledOn('2026-01-05')->create(['last_name' => 'Auer', 'insurer' => Insurer::Oegk]);
        Patient::factory()->enrolledOn('2026-01-05')->create(['last_name' => 'Berger', 'insurer' => Insurer::Svs]);

        $grouped = $this->billing->linesByInsurer(new Quarter(2026, 1));

        $this->assertSame(['ÖGK', 'SVS', 'KFA'], $grouped->keys()->all());
        $this->assertSame(['Auer', 'Zach'], $grouped['ÖGK']->map(fn (BillingLine $line): string => $line->patient->last_name)->all());
    }

    public function test_marking_a_quarter_as_billed_stores_one_record_per_patient(): void
    {
        $patient = Patient::factory()->enrolledOn('2025-04-15')->create(); // Q2 2025 = 1, Q1 2026 = 4
        $quarter = new Quarter(2026, 1);

        $this->assertSame(1, $this->billing->markAsBilled($quarter));

        $record = BillingRecord::query()->sole();
        $this->assertSame($patient->id, $record->patient_id);
        $this->assertSame(4, $record->programme_quarter);
        $this->assertSame(BillingCode::Annual, $record->code);
        $this->assertTrue($this->billing->linesFor($quarter)->sole()->isBilled());
    }

    public function test_marking_as_billed_twice_does_not_duplicate_records(): void
    {
        Patient::factory()->enrolledOn('2025-04-15')->create();
        $quarter = new Quarter(2026, 1);
        $this->billing->markAsBilled($quarter);

        Patient::factory()->enrolledOn('2026-02-01')->create();

        $this->assertSame(1, $this->billing->markAsBilled($quarter));
        $this->assertSame(2, BillingRecord::query()->count());
    }

    public function test_billed_lines_keep_their_code_when_the_enrolment_date_changes_later(): void
    {
        $patient = Patient::factory()->enrolledOn('2025-04-15')->create();
        $quarter = new Quarter(2026, 1);
        $this->billing->markAsBilled($quarter);

        $patient->update(['enrolled_at' => '2025-07-15']);

        $line = $this->billing->linesFor($quarter)->sole();
        $this->assertSame(BillingCode::Annual, $line->code);
        $this->assertSame(4, $line->programmeQuarter);
    }

    public function test_undo_removes_only_the_records_of_that_quarter(): void
    {
        Patient::factory()->enrolledOn('2025-04-15')->create();
        $this->billing->markAsBilled(new Quarter(2025, 4));
        $this->billing->markAsBilled(new Quarter(2026, 1));

        $this->assertSame(1, $this->billing->undoBilling(new Quarter(2026, 1)));
        $this->assertSame(1, BillingRecord::query()->count());
    }

    public function test_sv_numbers_are_stored_without_whitespace(): void
    {
        $patient = Patient::factory()->create(['sv_number' => '1237 010180']);

        $this->assertSame('1237010180', $patient->fresh()->sv_number);
    }
}
