<?php

namespace App\Support;

/**
 * Austrian social insurance number (Sozialversicherungsnummer), as printed on the e-card.
 *
 * Format: SSSC DDMMYY — three-digit serial, check digit, six-digit birth date part.
 * The birth date part is usually, but not always, the real date of birth.
 */
final class SvNumber
{
    /** @var array<int, int> Weights for digits 1–3 and 5–10 (the check digit at position 4 is skipped). */
    private const array WEIGHTS = [3, 7, 9, 5, 8, 4, 2, 1, 6];

    public static function normalize(string $value): string
    {
        return preg_replace('/\s+/', '', $value) ?? '';
    }

    public static function isValid(string $value): bool
    {
        $number = self::normalize($value);

        if (! preg_match('/^[1-9]\d{9}$/', $number)) {
            return false;
        }

        return self::checkDigit(substr($number, 0, 3), substr($number, 4, 6)) === (int) $number[3];
    }

    /**
     * Returns the check digit, or null when the combination yields 10 (such numbers are never issued).
     */
    public static function checkDigit(string $serial, string $birthDatePart): ?int
    {
        $digits = str_split($serial.$birthDatePart);
        $sum = 0;

        foreach ($digits as $index => $digit) {
            $sum += (int) $digit * self::WEIGHTS[$index];
        }

        $check = $sum % 11;

        return $check === 10 ? null : $check;
    }

    public static function birthDatePart(string $value): string
    {
        return substr(self::normalize($value), 4, 6);
    }

    /**
     * Formats the number the way it is printed on the e-card: "1237 010180".
     */
    public static function format(string $value): string
    {
        $number = self::normalize($value);

        return strlen($number) === 10 ? substr($number, 0, 4).' '.substr($number, 4) : $number;
    }
}
