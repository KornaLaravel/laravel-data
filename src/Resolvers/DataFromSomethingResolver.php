<?php

namespace Spatie\LaravelData\Resolvers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Contracts\PropertyMorphableData;
use Spatie\LaravelData\Enums\CustomCreationMethodType;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataConfig;

/**
 * @template TData of BaseData
 */
class DataFromSomethingResolver
{
    public function __construct(
        protected DataConfig $dataConfig,
        protected DataFromArrayResolver $dataFromArrayResolver,
    ) {
    }

    /**
     * @param class-string<TData> $class
     *
     * @return TData
     */
    public function execute(
        string $class,
        CreationContext $creationContext,
        mixed ...$payloads
    ): BaseData {
        if ($data = $this->createFromCustomCreationMethod($class, $creationContext, $payloads)) {
            return $data;
        }

        $pipeline = $this->dataConfig->getResolvedDataPipeline($class);

        $payloadCount = count($payloads);

        if ($payloadCount === 0 || $payloadCount === 1) {
            $properties = $pipeline->execute($payloads[0] ?? [], $creationContext);

            return $this->dataFromArray($class, $creationContext, $payloads, $properties);
        }

        $properties = [];

        foreach ($payloads as $payload) {
            foreach ($pipeline->execute($payload, $creationContext) as $key => $value) {
                if (array_key_exists($key, $properties) && ($value === null || $value instanceof Optional)) {
                    continue;
                }

                $properties[$key] = $value;
            }
        }

        return $this->dataFromArray($class, $creationContext, $payloads, $properties);
    }

    protected function createFromCustomCreationMethod(
        string $class,
        CreationContext $creationContext,
        array $payloads
    ): ?BaseData {
        if ($creationContext->disableMagicalCreation) {
            return null;
        }

        $customCreationMethods = $this->dataConfig
            ->getDataClass($class)
            ->methods;

        $method = null;

        foreach ($customCreationMethods as $customCreationMethod) {
            if ($customCreationMethod->customCreationMethodType !== CustomCreationMethodType::Object) {
                continue;
            }

            if (
                $creationContext->ignoredMagicalMethods !== null
                && in_array($customCreationMethod->name, $creationContext->ignoredMagicalMethods)
            ) {
                continue;
            }

            if ($customCreationMethod->accepts(...$payloads)) {
                $method = $customCreationMethod;

                break;
            }
        }

        if ($method === null) {
            return null;
        }

        $pipeline = $this->dataConfig->getResolvedDataPipeline($class);

        foreach ($payloads as $payload) {
            if ($payload instanceof Request) {
                // Solely for the purpose of validation
                $pipeline->execute($payload, $creationContext);
            }
        }

        foreach ($method->parameters as $index => $parameter) {
            if ($parameter->type->type->isCreationContext()) {
                $payloads[$index] = $creationContext;
            }
        }

        $methodName = $method->name;

        return $class::$methodName(...$payloads);
    }

    protected function dataFromArray(
        string $class,
        CreationContext $creationContext,
        array $payloads,
        array $properties,
    ): BaseData {
        $dataClass = $this->dataConfig->getDataClass($class);

        if ($dataClass->isAbstract && $dataClass->propertyMorphable) {
            $morphableProperties = Arr::only($properties, $dataClass->propertyMorphablePropertyNames);

            /**
             * @var class-string<PropertyMorphableData> $class
             */
            if (
                count($morphableProperties) === count($dataClass->propertyMorphablePropertyNames)
                && $morph = $class::morph($morphableProperties)
            ) {
                return $this->execute($morph, $creationContext, ...$payloads);
            }
        }

        return $this->dataFromArrayResolver->execute($class, $properties);
    }
}
