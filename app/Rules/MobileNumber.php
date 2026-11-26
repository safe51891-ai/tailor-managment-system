<?php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class MobileNumber implements Rule
{
    public const HTML_PATTERN = '(?:03[0-9]{9}|\+923[0-9]{9}|\+9665[0-9]{8}|\+(?!92|966)[1-9][0-9]{7,14})';

    public function passes($attribute, $value)
    {
        return is_string($value) && preg_match('/\A'.self::HTML_PATTERN.'\z/', $value) === 1;
    }

    public function message()
    {
        return 'Enter a Pakistani mobile number starting with 03 (11 digits), or an international number with + and its country code, e.g. +966512345678. Use no spaces.';
    }

    public static function normalize($value)
    {
        // Keep Pakistan numbers in the familiar local format for consistent lookup.
        return preg_match('/\A\+923[0-9]{9}\z/', $value) ? '0'.substr($value, 3) : $value;
    }

    public static function search($value)
    {
        $compact = preg_replace('/[\s()\-]+/u', '', trim($value));
        if (preg_match('/\A00923[0-9]{9}\z/', $compact)) $compact = '+'.substr($compact, 2);
        return self::normalize($compact !== '' ? $compact : $value);
    }
}
