<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Dingo\Api\Http\FormRequest as DingoFormRequest;
use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use Knuckles\Scribe\Extracting\FindsFormRequestForMethod;
use Knuckles\Scribe\Extracting\ParsesValidationRules;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\Globals;
use ReflectionClass;
use ReflectionFunctionAbstract;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;

class GetFromFormRequestBase extends Strategy
{
    use ParsesValidationRules, FindsFormRequestForMethod;

    protected string $customParameterDataMethodName = '';

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        return $this->getParametersFromFormRequest($endpointData->method, $endpointData->route);
    }

    public function getParametersFromFormRequest(ReflectionFunctionAbstract $method, Route $route): array
    {
        $formRequestReflectionClasses = $this->getFormRequestReflectionClasses($method);
        if ($formRequestReflectionClasses->isEmpty()) {
            return [];
        }

        $usable = $formRequestReflectionClasses->filter(
            fn ($formRequestReflectionClass) => $this->isFormRequestMeantForThisStrategy($formRequestReflectionClass)
        );

        if ($usable->isEmpty()) {
            return [];
        }

        return $usable->map(function ($reflection) use ($route, $method) {
            $className = $reflection->getName();

            if (Globals::$__instantiateFormRequestUsing) {
                return call_user_func_array(Globals::$__instantiateFormRequestUsing, [$className, $route, $method]);
            }

            return new $className;
        })->each(function ($formRequest) use ($route) {
            // Set the route properly so it works for users who have code that checks for the route.
            /** @var LaravelFormRequest|DingoFormRequest $formRequest */
            $formRequest->setRouteResolver(function () use ($formRequest, $route) {
                // Also need to bind the request to the route in case their code tries to inspect current request
                return $route->bind($formRequest);
            });
            $formRequest->server->set('REQUEST_METHOD', $route->methods()[0]);
        })->reduce(function ($carry, $formRequest) {
            $validationRules = collect($this->getRouteValidationRules($formRequest))->map(function ($rules, $key) {
                if(is_string($rules)) {
                    $rules = collect(explode('|', $rules))->toArray();
                }
                return $rules;
            });
            $parametersFromFormRequest = $this->getParametersFromValidationRules(
                $validationRules->toArray(),
                $this->getCustomParameterData($formRequest)
            );

            return array_merge($carry, $this->normaliseArrayAndObjectParameters($parametersFromFormRequest));
        }, []);
    }

    /**
     * @param LaravelFormRequest|DingoFormRequest $formRequest
     *
     * @return mixed
     */
    protected function getRouteValidationRules($formRequest)
    {
        if (method_exists($formRequest, 'validator')) {
            $validationFactory = app(ValidationFactory::class);

            return app()->call([$formRequest, 'validator'], [$validationFactory])
                ->getRules();
        } elseif (method_exists($formRequest, 'rules')) {
            return app()->call([$formRequest, 'rules']);
        }

        return [];
    }

    /**
     * @param LaravelFormRequest|DingoFormRequest $formRequest
     */
    protected function getCustomParameterData($formRequest)
    {
        if (method_exists($formRequest, $this->customParameterDataMethodName)) {
            return call_user_func_array([$formRequest, $this->customParameterDataMethodName], []);
        }

        c::warn("No {$this->customParameterDataMethodName}() method found in " . get_class($formRequest) . ". Scribe will only be able to extract basic information from the rules() method.");

        return [];
    }

    protected function getMissingCustomDataMessage($parameterName)
    {
        return "No data found for parameter '$parameterName' in your {$this->customParameterDataMethodName}() method. Add an entry for '$parameterName' so you can add a description and example.";
    }

    protected function isFormRequestMeantForThisStrategy(ReflectionClass $formRequestReflectionClass): bool
    {
        return $formRequestReflectionClass->hasMethod($this->customParameterDataMethodName);
    }

}
