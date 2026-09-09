<?php

if (!function_exists('currency_symbol')) {
    function currency_symbol($currency): string
    {
        return match (strtolower((string) $currency)) {
            'gbp' => '£',
            'usd' => '$',
            'eur' => '€',
            'jpy' => '¥',
            default => strtoupper((string) $currency),
        };
    }
}

if (!function_exists('format_currency')) {
    function format_currency($amount, $currency): string
    {
        return currency_symbol($currency) . number_format((float) $amount, 2);
    }
}
