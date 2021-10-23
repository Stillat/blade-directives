<?php

namespace Stillat\BladeDirectives;

class DirectiveContainer
{

    /**
     * The parameters.
     *
     * @var array
     */
    public $parameters = [];

    /**
     * The directive's parameters.
     *
     * @param array $parameters
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Replaces parameters within the code sample with their PHP equivalents.
     *
     * @param string $code
     * @return string
     */
    public function compile($code)
    {
        $remapped = [];
        $escaped = [];

        foreach ($this->parameters as $paramName => $value) {
            $remapped['$'.$paramName] = $value;
            $key = '_'.sha1($paramName);

            // Allows for \$varName syntax within compile templates to escape variable names.
            $code = str_replace('\$'.$paramName, $key, $code);

            $escaped[$key] = '$'.$paramName;
        }

        $code = strtr($code, $remapped);

        return strtr($code, $escaped);
    }
}