<?php

namespace Tests\Unit;

use App\Support\Quarter;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuarterTest extends TestCase
{
    public static function datesProvider(): array
    {
        return [
            'first day of year' => ['2026-01-01', 2026, 1],
            'last day of Q1' => ['2026-03-31', 2026, 1],
            'first day of Q2' => ['2026-04-01', 2026, 2],
            'mid Q3' => ['2026-08-15', 2026, 3],
            'last day of year' => ['2026-12-31', 2026, 4],
        ];
    }

    #[DataProvider('datesProvider')]
    public function test_it_resolves_the_quarter_of_a_date(string $date, int $year, int $number): void
    {
        $quarter = Quarter::fromDate(CarbonImmutable::parse($date));

        $this->assertSame($year, $quarter->year);
        $this->assertSame($number, $quarter->number);
    }

    public function test_it_knows_its_first_and_last_day(): void
    {
        $quarter = new Quarter(2028, 1);

        $this->assertSame('2028-01-01', $quarter->start()->toDateString());
        $this->assertSame('2028-03-31', $quarter->end()->toDateString());
        $this->assertSame('2026-12-31', (new Quarter(2026, 4))->end()->toDateString());
    }

    public function test_next_and_previous_cross_year_boundaries(): void
    {
        $this->assertEquals(new Quarter(2027, 1), (new Quarter(2026, 4))->next());
        $this->assertEquals(new Quarter(2026, 4), (new Quarter(2027, 1))->previous());
    }

    public function test_it_counts_quarters_between_two_quarters(): void
    {
        $this->assertSame(0, (new Quarter(2026, 2))->quartersSince(new Quarter(2026, 2)));
        $this->assertSame(3, (new Quarter(2027, 1))->quartersSince(new Quarter(2026, 2)));
        $this->assertSame(-1, (new Quarter(2026, 1))->quartersSince(new Quarter(2026, 2)));
    }

    public function test_it_round_trips_through_its_key(): void
    {
        $quarter = new Quarter(2026, 3);

        $this->assertSame('2026-3', $quarter->key());
        $this->assertEquals($quarter, Quarter::fromKey('2026-3'));
        $this->assertSame('Q3 2026', $quarter->label());
    }

    public function test_it_rejects_invalid_quarter_numbers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Quarter(2026, 5);
    }

    public function test_it_rejects_malformed_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Quarter::fromKey('2026-Q3');
    }
}
