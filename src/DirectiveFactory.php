<?php

namespace Stillat\BladeDirectives;

use Illuminate\Support\Facades\Blade;
use ReflectionException;
use ReflectionFunction;
use ReflectionParameter;
use Stillat\Primitives\Parser;

class DirectiveFactory
{
    /**
     * The Parser instance.
     *
     * @var Parser
     */
    protected $parser = null;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Registers a handler for a custom Blade directive.
     *
     * Directive handlers will have access to the provided parameters through $this->parameters.
     *
     * @param  string  $name
     * @param  callable  $handler
     */
    public function params($name, callable $handler)
    {
        Blade::directive($name, $this->getParamsClosure($handler));
    }

    /**
     * Register a handler for a custom Blade directive.
     *
     * @param  string  $name
     * @param  callable  $handler
     */
    public function make($name, callable $handler)
    {
        Blade::directive($name, $this->getFactoryClosure($handler, false));
    }

    /**
     * Register a handler for a custom compiled Blade directive.
     *
     * @param  string  $name
     * @param  callable  $handler
     */
    public function compile($name, $handler)
    {
        Blade::directive($name, $this->getFactoryClosure($handler, true));
    }

    private function getParamsClosure($handler)
    {
        return function ($expression) use ($handler) {
            $expressionParameters = $this->parser->safeSplitString($expression);

            $paramCompiler = new DirectiveContainer();
            $paramCompiler->setParameters($expressionParameters);
            $handler = $handler->bindTo($paramCompiler);

            return call_user_func_array($handler, [$expression]);
        };
    }

    private function getFactoryClosure($handler, $compileResult)
    {
        return function ($expression) use ($handler, $compileResult) {
            $handlerRef = new ReflectionFunction($handler);

            $expressionParameters = $this->parser->safeSplitString($expression);
            $associatedParams = $this->associateParameters($handlerRef->getParameters(), $expressionParameters);

            $paramCompiler = new DirectiveContainer();
            $paramCompiler->setParameters($associatedParams);
            $handler = $handler->bindTo($paramCompiler);

            $result = call_user_func_array($handler, $associatedParams);

            if ($compileResult) {
                return $paramCompiler->compile($result);
            }

            return $result;
        };
    }

    /**
     * Associates the reflected parameters with their parsed values.
     *
     * @param  ReflectionParameter[]  $directiveParameters
     * @param  string[]  $expressionParameters
     * @return array
     *
     * @throws ReflectionException
     */
    private function associateParameters($directiveParameters, $expressionParameters)
    {
        if (empty($directiveParameters)) {
            return [];
        }

        $associatedParameters = [];

        for ($i = 0; $i < count($directiveParameters); $i++) {
            $dParam = $directiveParameters[$i];
            $eParam = 'null';

            if (isset($expressionParameters[$i])) {
                $eParam = $expressionParameters[$i];
            }

            $defaultExpression = null;

            if ($dParam->isDefaultValueAvailable()) {
                $defaultConst = $dParam->getDefaultValueConstantName();

                if ($defaultConst !== null) {
                    $defaultExpression = $defaultConst;
                } else {
                    $defaultExpression = var_export($dParam->getDefaultValue(), true);
                }
            }

            if ($defaultExpression !== null) {
                $associatedParameters[$dParam->name] = '(('.$eParam.') ?? ('.$defaultExpression.'))';
            } else {
                $associatedParameters[$dParam->name] = $eParam;
            }
        }

        return $associatedParameters;
    }
}
