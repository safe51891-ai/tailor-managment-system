<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class PersonName implements Rule
{
    public const HTML_PATTERN = "(?=.*\\p{L})[\\p{L}\\p{M} .'’\\-]+";

    public function passes($attribute, $value)
    {
        return is_string($value) && preg_match("/\\A(?=.*\\p{L})[\\p{L}\\p{M} .'’\\-]+\\z/u", $value) === 1;
    }

    public function message()
    {
        return 'The :attribute must contain letters only, with spaces, apostrophes, hyphens or periods allowed. Numbers and other symbols are not allowed.';
    }
}
