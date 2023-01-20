<?php

namespace Spatie\LaravelData\Attributes\Validation;

use Attribute;
use Spatie\LaravelData\Support\Validation\ValidationPath;

#[Attribute(Attribute::TARGET_PROPERTY)]
class File extends StringValidationAttribute
{
    public static function keyword(): string
    {
        return 'file';
    }

    public function parameters(ValidationPath $path): array
    {
        return [];
    }
}
