<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * A calendar quarter (Q1 = January–March, …, Q4 = October–December).
 */
final readonly class Quarter
{
    public function __construct(
        public int $year,
        public int $number,
    ) {
        if ($number < 1 || $number > 4) {
            throw new InvalidArgumentException("Quarter number must be between 1 and 4, got {$number}.");
        }
    }

    public static function fromDate(CarbonInterface $date): self
    {
        return new self($date->year, intdiv($date->month - 1, 3) + 1);
    }

    public static function current(): self
    {
        return self::fromDate(CarbonImmutable::now());
    }

    /**
     * Parse a key in the form "2026-3" as produced by {@see key()}.
     */
    public static function fromKey(string $key): self
    {
        if (! preg_match('/^(\d{4})-([1-4])$/', $key, $matches)) {
            throw new InvalidArgumentException("Invalid quarter key: {$key}");
        }

        return new self((int) $matches[1], (int) $matches[2]);
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::create($this->year, ($this->number - 1) * 3 + 1, 1)->startOfDay();
    }

    public function end(): CarbonImmutable
    {
        return $this->start()->addMonths(3)->subDay()->endOfDay();
    }

    public function next(): self
    {
        return $this->number === 4 ? new self($this->year + 1, 1) : new self($this->year, $this->number + 1);
    }

    public function previous(): self
    {
        return $this->number === 1 ? new self($this->year - 1, 4) : new self($this->year, $this->number - 1);
    }

    /**
     * Number of quarters from $other to this quarter (negative if $other is later).
     */
    public function quartersSince(self $other): int
    {
        return $this->ordinal() - $other->ordinal();
    }

    public function key(): string
    {
        return "{$this->year}-{$this->number}";
    }

    public function label(): string
    {
        return "Q{$this->number} {$this->year}";
    }

    private function ordinal(): int
    {
        return $this->year * 4 + ($this->number - 1);
    }
}
