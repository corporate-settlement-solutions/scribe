<?php

namespace Knuckles\Scribe\Extracting;

use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use Dingo\Api\Http\FormRequest as DingoFormRequest;
use Illuminate\Support\Collection;
use Reflection;
use ReflectionClass;
use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionUnionType;

trait FindsFormRequestForMethod
{
    protected function getFormRequestReflectionClasses(ReflectionFunctionAbstract $method): Collection
    {
        return collect($method->getParameters())->reject(
            fn ($argument) => $argument->getType() === null || $argument->getType() instanceof ReflectionUnionType
        )->filter(
            fn ($argument) => class_exists($argument->getType()->getName())
        )->map(
            fn ($argument) => rescue(fn () => new ReflectionClass($argument->getType()->getName()), null)
        )->filter()->filter(
            fn ($reflection) => (class_exists(LaravelFormRequest::class) && $reflection->isSubclassOf(LaravelFormRequest::class))
                || (class_exists(DingoFormRequest::class) && $reflection->isSubclassOf(DingoFormRequest::class))
        );
    }
}
