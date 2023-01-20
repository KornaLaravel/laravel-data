<?php

namespace Spatie\LaravelData\Attributes\Validation;

use Attribute;
use Spatie\LaravelData\Support\Validation\ValidationPath;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Size extends StringValidationAttribute
{
    public function __construct(protected int $size)
    {
    }

    public static function keyword(): string
    {
        return 'size';
    }

    public function parameters(ValidationPath $path): array
    {
        return [$this->size];
    }
}
