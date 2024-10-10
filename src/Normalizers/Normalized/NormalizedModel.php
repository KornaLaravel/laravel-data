<?php

namespace Spatie\LaravelData\Normalizers\Normalized;

use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionProperty;
use Spatie\LaravelData\Attributes\LoadRelation;
use Spatie\LaravelData\Support\DataProperty;

class NormalizedModel implements Normalized
{
    protected array $properties = [];

    protected ReflectionProperty $castsProperty;

    protected ReflectionProperty $attributesProperty;

    public function __construct(
        protected Model $model,
    ) {
    }

    public function getProperty(string $name, DataProperty $dataProperty): mixed
    {
        $value = array_key_exists($name, $this->properties)
            ? $this->properties[$name]
            : $this->fetchNewProperty($name, $dataProperty);

        if ($value === null && ! $dataProperty->type->isNullable) {
            return UnknownProperty::create();
        }

        return $value;
    }

    protected function fetchNewProperty(string $name, DataProperty $dataProperty): mixed
    {
        if ($dataProperty->attributes->contains(fn (object $attribute) => $attribute::class === LoadRelation::class)) {
            if (method_exists($this->model, $name)) {
                $this->model->loadMissing($name);
            }
        }

        if ($this->model->relationLoaded($name)) {
            return $this->properties[$name] = $this->model->getRelation($name);
        }

        if (!$this->model->isRelation($name)) {
            try {
                $propertyName = $this->model::$snakeAttributes ? Str::snake($name) : $name;
                return $this->properties[$name] = $this->model->getAttribute($propertyName);
            } catch (MissingAttributeException) {
                // Fallback if missing Attribute
            }
        }

        return $this->properties[$name] = UnknownProperty::create();
    }

    protected function hasModelAttribute(string $name): bool
    {
        if (method_exists($this->model, 'hasAttribute')) {
            return $this->model->hasAttribute($name);
        }

        // TODO: remove this once we stop supporting Laravel 10
        if (! isset($this->attributesProperty)) {
            $this->attributesProperty = new ReflectionProperty($this->model, 'attributes');
        }

        if (! isset($this->castsProperty)) {
            $this->castsProperty = new ReflectionProperty($this->model, 'casts');
        }

        return array_key_exists($name, $this->attributesProperty->getValue($this->model)) ||
            array_key_exists($name, $this->castsProperty->getValue($this->model)) ||
            $this->model->hasGetMutator($name) ||
            $this->model->hasAttributeMutator($name);
    }
}
