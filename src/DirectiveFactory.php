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

    private function getParametersForClosure($handler, $expression, $onlyExpressionArgs = false)
    {
        $handlerRef = new ReflectionFunction($handler);

        $expressionParameters = $this->parser->safeSplitNamedString($expression);

        return $this->associateParameters($handlerRef->getParameters(), $expressionParameters, $onlyExpressionArgs);
    }

    private function getParamsClosure($handler)
    {
        return function ($expression) use ($handler) {
            $expressionParameters = $this->getParametersForClosure($handler, $expression, true);

            $paramCompiler = new DirectiveContainer();
            $paramCompiler->setParameters($expressionParameters);
            $handler = $handler->bindTo($paramCompiler);

            return call_user_func_array($handler, [$expression]);
        };
    }

    private function getFactoryClosure($handler, $compileResult)
    {
        return function ($expression) use ($handler, $compileResult) {
            $associatedParams = $this->getParametersForClosure($handler, $expression);

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
     * @param  bool  $returnOnlyExpressionArgs
     * @return array
     *
     * @throws ReflectionException
     */
    private function associateParameters($directiveParameters, $expressionParameters, $returnOnlyExpressionArgs = false)
    {
        if (empty($directiveParameters)) {
            return [];
        }

        $hasNamedArgs = false;
        $namedArgsStart = null;
        $argsToUse = [];

        for ($i = 0; $i < count($expressionParameters); $i++) {
            if ($expressionParameters[$i][1] != null) {
                $hasNamedArgs = true;
                $namedArgsStart = $i;
                break;
            }
        }

        if ($hasNamedArgs) {
            $namedArgs = array_splice($expressionParameters, $namedArgsStart);
            $argsToMatch = array_slice($directiveParameters, $namedArgsStart);

            foreach ($expressionParameters as $arg) {
                $argsToUse[] = $arg[0];
            }

            foreach ($argsToMatch as $callbackArg) {
                $didFind = false;

                foreach ($namedArgs as $arg) {
                    if ($callbackArg->name == $arg[1]) {
                        $argsToUse[] = $arg[0];
                        $didFind = true;
                        break;
                    }
                }

                if (! $didFind) {
                    $argsToUse[] = $this->getDefaultExpression($callbackArg);
                }
            }
        } else {
            foreach ($expressionParameters as $arg) {
                $argsToUse[] = $arg[0];
            }
        }

        if ($returnOnlyExpressionArgs) {
            return $argsToUse;
        }

        for ($i = 0; $i < count($directiveParameters); $i++) {
            $dParam = $directiveParameters[$i];
            $eParam = 'null';

            if (isset($argsToUse[$i])) {
                $eParam = $argsToUse[$i];
            }

            $defaultExpression = $this->getDefaultExpression($dParam);

            if ($defaultExpression !== null) {
                $associatedParameters[$dParam->name] = '(('.$eParam.') ?? ('.$defaultExpression.'))';
            } else {
                $associatedParameters[$dParam->name] = $eParam;
            }
        }

        return $associatedParameters;
    }

    private function getDefaultExpression(ReflectionParameter $parameter)
    {
        $defaultExpression = null;

        if ($parameter->isDefaultValueAvailable()) {
            $defaultConst = $parameter->getDefaultValueConstantName();

            if ($defaultConst !== null) {
                $defaultExpression = $defaultConst;
            } else {
                $defaultExpression = var_export($parameter->getDefaultValue(), true);
            }
        }

        return $defaultExpression;
    }
}
