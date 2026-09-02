<?php

namespace App\Support\Phone;

use InvalidArgumentException;

/**
 * Normalize Uzbekistan phone numbers to E.164 (+998XXXXXXXXX).
 */
final class PhoneNormalizer
{
    public static function normalize(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException('Phone number is required.');
        }

        if (str_starts_with($digits, '998') && strlen($digits) === 12) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '8') && strlen($digits) === 10) {
            return '+998'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '+998'.$digits;
        }

        if (str_starts_with($digits, '998') && strlen($digits) > 12) {
            return '+'.substr($digits, 0, 12);
        }

        if (str_starts_with($input, '+') && strlen($digits) >= 12) {
            return '+'.$digits;
        }

        throw new InvalidArgumentException('Invalid Uzbekistan phone number format.');
    }
}
