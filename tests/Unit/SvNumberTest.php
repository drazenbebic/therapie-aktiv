<?php

namespace Tests\Unit;

use App\Support\SvNumber;
use PHPUnit\Framework\TestCase;

class SvNumberTest extends TestCase
{
    public function test_it_accepts_a_number_with_a_correct_check_digit(): void
    {
        // 123 + check digit + 010180: (1*3 + 2*7 + 3*9 + 0*5 + 1*8 + 0*4 + 1*2 + 8*1 + 0*6) % 11 = 62 % 11 = 7
        $this->assertTrue(SvNumber::isValid('1237010180'));
    }

    public function test_it_ignores_whitespace(): void
    {
        $this->assertTrue(SvNumber::isValid('1237 010180'));
        $this->assertSame('1237010180', SvNumber::normalize(' 1237 010180 '));
    }

    public function test_it_rejects_a_wrong_check_digit(): void
    {
        $this->assertFalse(SvNumber::isValid('1238010180'));
    }

    public function test_it_rejects_wrong_lengths_and_letters(): void
    {
        $this->assertFalse(SvNumber::isValid('123701018'));
        $this->assertFalse(SvNumber::isValid('12370101800'));
        $this->assertFalse(SvNumber::isValid('12A7010180'));
        $this->assertFalse(SvNumber::isValid(''));
    }

    public function test_it_rejects_a_number_starting_with_zero(): void
    {
        // Serial numbers start at 100; leading zero is never issued.
        $this->assertFalse(SvNumber::isValid('0127010180'));
    }

    public function test_it_reads_the_birth_date_part(): void
    {
        $this->assertSame('010180', SvNumber::birthDatePart('1237010180'));
    }

    public function test_it_computes_the_check_digit(): void
    {
        $this->assertSame(7, SvNumber::checkDigit('123', '010180'));
    }
}
