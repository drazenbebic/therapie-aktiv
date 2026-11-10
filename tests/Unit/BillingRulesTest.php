<?php

namespace Tests\Unit;

use App\Billing\BillingCode;
use App\Billing\BillingRules;
use App\Support\Quarter;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BillingRulesTest extends TestCase
{
    public function test_the_enrolment_quarter_is_programme_quarter_one(): void
    {
        $enrolled = CarbonImmutable::parse('2026-02-15');

        $this->assertSame(1, BillingRules::programmeQuarter($enrolled, new Quarter(2026, 1)));
    }

    public function test_enrolling_on_the_last_day_of_a_quarter_still_counts_that_quarter(): void
    {
        // 2 days before the end of Q1: Q1 is billable, Q2 a few days later too.
        $enrolled = CarbonImmutable::parse('2026-03-29');

        $this->assertSame(1, BillingRules::programmeQuarter($enrolled, new Quarter(2026, 1)));
        $this->assertSame(2, BillingRules::programmeQuarter($enrolled, new Quarter(2026, 2)));
    }

    public function test_quarters_before_enrolment_are_not_part_of_the_programme(): void
    {
        $enrolled = CarbonImmutable::parse('2026-05-01');

        $this->assertNull(BillingRules::programmeQuarter($enrolled, new Quarter(2026, 1)));
    }

    public function test_the_counter_keeps_running_across_years(): void
    {
        $enrolled = CarbonImmutable::parse('2025-11-20'); // Q4 2025 = 1

        $this->assertSame(4, BillingRules::programmeQuarter($enrolled, new Quarter(2026, 3)));
        $this->assertSame(5, BillingRules::programmeQuarter($enrolled, new Quarter(2026, 4)));
    }

    public static function codesProvider(): array
    {
        return [
            [1, BillingCode::Regular],
            [2, BillingCode::Regular],
            [3, BillingCode::Regular],
            [4, BillingCode::Annual],
            [5, BillingCode::Regular],
            [7, BillingCode::Regular],
            [8, BillingCode::Annual],
            [12, BillingCode::Annual],
        ];
    }

    #[DataProvider('codesProvider')]
    public function test_every_fourth_programme_quarter_is_billed_as_97a(int $programmeQuarter, BillingCode $expected): void
    {
        $this->assertSame($expected, BillingRules::codeFor($programmeQuarter));
    }

    public function test_billing_code_values_match_the_insurer_positions(): void
    {
        $this->assertSame('97', BillingCode::Regular->value);
        $this->assertSame('97a', BillingCode::Annual->value);
    }
}
