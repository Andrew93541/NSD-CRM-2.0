<?php

namespace App\Support;

class IndianCurrencyWords
{
    private const ONES = [
        0 => '',
        1 => 'One',
        2 => 'Two',
        3 => 'Three',
        4 => 'Four',
        5 => 'Five',
        6 => 'Six',
        7 => 'Seven',
        8 => 'Eight',
        9 => 'Nine',
        10 => 'Ten',
        11 => 'Eleven',
        12 => 'Twelve',
        13 => 'Thirteen',
        14 => 'Fourteen',
        15 => 'Fifteen',
        16 => 'Sixteen',
        17 => 'Seventeen',
        18 => 'Eighteen',
        19 => 'Nineteen',
    ];

    private const TENS = [
        0 => '',
        2 => 'Twenty',
        3 => 'Thirty',
        4 => 'Forty',
        5 => 'Fifty',
        6 => 'Sixty',
        7 => 'Seventy',
        8 => 'Eighty',
        9 => 'Ninety',
    ];

    /**
     * Convert a numeric INR amount into Indian English words (Crore, Lakh, Thousand, Hundred, Paise).
     *
     * Example:
     * 4777617.05 -> "Rupees Forty Seven Lakh Seventy Seven Thousand Six Hundred Seventeen and Five Paise Only"
     * 4777617.00 -> "Rupees Forty Seven Lakh Seventy Seven Thousand Six Hundred Seventeen Only"
     */
    public static function toWords(float $amount): string
    {
        $amount = max(0.0, round($amount, 2));
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        if ($paise === 100) {
            $rupees += 1;
            $paise = 0;
        }

        $rupeeWords = $rupees === 0 ? 'Zero' : self::convertInteger($rupees);

        if ($paise > 0) {
            $paiseWords = self::convertTwoDigits($paise);

            return "Rupees {$rupeeWords} and {$paiseWords} Paise Only";
        }

        return "Rupees {$rupeeWords} Only";
    }

    /**
     * Format a number using Indian digit grouping (e.g. 47,77,617.05).
     */
    public static function formatIndianNumber(float $amount, int $decimals = 2): string
    {
        $isNegative = $amount < 0;
        $amount = abs($amount);
        $formatted = number_format($amount, $decimals, '.', '');
        $parts = explode('.', $formatted);
        $intPart = $parts[0];
        $decPart = isset($parts[1]) && $decimals > 0 ? '.' . $parts[1] : '';

        if (strlen($intPart) > 3) {
            $lastThree = substr($intPart, -3);
            $restUnits = substr($intPart, 0, -3);
            $restUnits = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $restUnits);
            $intPart = $restUnits . ',' . $lastThree;
        }

        return ($isNegative ? '-' : '') . $intPart . $decPart;
    }

    private static function convertInteger(int $number): string
    {
        if ($number <= 0) {
            return '';
        }

        $parts = [];

        $crores = intdiv($number, 10000000);
        $number %= 10000000;
        if ($crores > 0) {
            $parts[] = self::convertInteger($crores) . ' Crore';
        }

        $lakhs = intdiv($number, 100000);
        $number %= 100000;
        if ($lakhs > 0) {
            $parts[] = self::convertTwoDigits($lakhs) . ' Lakh';
        }

        $thousands = intdiv($number, 1000);
        $number %= 1000;
        if ($thousands > 0) {
            $parts[] = self::convertTwoDigits($thousands) . ' Thousand';
        }

        $hundreds = intdiv($number, 100);
        $remainder = $number % 100;
        if ($hundreds > 0) {
            $parts[] = self::ONES[$hundreds] . ' Hundred';
        }

        if ($remainder > 0) {
            $parts[] = self::convertTwoDigits($remainder);
        }

        return implode(' ', array_filter($parts));
    }

    private static function convertTwoDigits(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n] ?? '';
        }

        $ten = intdiv($n, 10);
        $one = $n % 10;

        return trim((self::TENS[$ten] ?? '') . ($one > 0 ? ' ' . self::ONES[$one] : ''));
    }
}

