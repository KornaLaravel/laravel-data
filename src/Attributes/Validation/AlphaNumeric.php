<?php

namespace Spatie\LaravelData\Attributes\Validation;

use Attribute;
use Spatie\LaravelData\Support\Validation\ValidationPath;

#[Attribute(Attribute::TARGET_PROPERTY)]
class AlphaNumeric extends StringValidationAttribute
{
    public static function keyword(): string
    {
        return 'alpha_num';
    }

    public function parameters(ValidationPath $path): array
    {
        return [];
    }
}
