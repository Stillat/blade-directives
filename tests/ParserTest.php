<?php

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Blade;
use Stillat\BladeDirectives\Support\Facades\Directive;
use Stillat\BladeDirectives\DirectiveContainer;

class ParserTest extends TestCase
{

    public function test_directives_receive_parameters()
    {
Directive::make('test', function ($param1, $param2, $param3, $param4) {
    return $param1.'---'.$param2.'---'.$param3.'---'.$param4;
});

        $template = <<<'BLADE'
@test('hello', 'world', [1,2,3,'four', 'five' => [1, env('something', 'default')]], 'six')
BLADE;

        $result = Blade::compileString($template);

        $this->assertSame("'hello'---'world'---[1, 2, 3, 'four', 'five' => [1, env('something', 'default')]]---'six'", $result);
    }

    public function test_param_directives_receives_expression_but_has_access_to_parameters()
    {
        $directiveExpression = '';
        $directiveParams = [];

        Directive::params('test', function ($expression) use (&$directiveExpression, &$directiveParams) {
            /** @var $this DirectiveContainer */

            $directiveExpression = $expression;
            $directiveParams = $this->parameters;

            return 'Hello';
        });

        $template = <<<'BLADE'
@test('hello', 'world', [1,2,3,'four', 'five' => [1, env('something', 'default')]], 'six')
BLADE;

        $result = Blade::compileString($template);

        $this->assertSame('Hello', $result);
        $this->assertSame("'hello', 'world', [1,2,3,'four', 'five' => [1, env('something', 'default')]], 'six'", $directiveExpression);
        $this->assertCount(4, $directiveParams);

        $this->assertSame("'hello'", $directiveParams[0]);
        $this->assertSame("'world'", $directiveParams[1]);
        $this->assertSame("[1, 2, 3, 'four', 'five' => [1, env('something', 'default')]]", $directiveParams[2]);
        $this->assertSame("'six'", $directiveParams[3]);
    }

    public function test_directives_can_define_default_values()
    {
        Directive::make('test', function ($name, $default = '1234') {
            return $name.'---'.$default;
        });

        $result = Blade::compileString('@test($varName)');

        $this->assertSame('$varName---((null) ?? (\'1234\'))', $result);


        $result = Blade::compileString('@test($varName, $anotherVarName)');

        $this->assertSame('$varName---(($anotherVarName) ?? (\'1234\'))', $result);


        Directive::make('test', function ($name, $default = '1234', $another = 1234) {
            return $name.'---'.$default.'---'.$another;
        });

        $result = Blade::compileString('@test($varName)');

        $this->assertSame('$varName---((null) ?? (\'1234\'))---((null) ?? (1234))', $result);


        $result = Blade::compileString('@test($varName, "test")');

        $this->assertSame('$varName---(("test") ?? (\'1234\'))---((null) ?? (1234))', $result);
        $result = Blade::compileString('@test($varName, "test", $varName)');

        $this->assertSame('$varName---(("test") ?? (\'1234\'))---(($varName) ?? (1234))', $result);
    }

    public function test_parameters_can_be_compiled()
    {
        Directive::compile('test', function ($value = []) {
           return '<?php foreach ($value as $testVar) {
    echo $testVar;
}';
        });

        $expected = <<<'RESULT'
<?php foreach (((array_merge(range("a", "z"), [1, 2, 3, 4, 5], $anotherVarName)) ?? (array (
))) as $testVar) {
    echo $testVar;
}
RESULT;

        $result = Blade::compileString('@test(array_merge(range("a", "z"), [1, 2, 3, 4, 5], $anotherVarName))');

        $this->assertSame($expected, $result);
    }

    public function test_variable_names_can_be_escaped()
    {
        Directive::compile('test', function ($value = []) {
            return '<?php
if (isset(\$value)) { /* Just testing we can output the var name without it being replaced. */ }
foreach ($value as $testVar) {
    echo $testVar;
}

if (isset(\$value)) { /* Just testing we can output the var name without it being replaced. */ }

$something = $value;
';
        });

        $expected = <<<'RESULT'
<?php
if (isset($value)) { /* Just testing we can output the var name without it being replaced. */ }
foreach (((array_merge(range("a", "z"), [1, 2, 3, 4, 5], $anotherVarName)) ?? (array (
))) as $testVar) {
    echo $testVar;
}

if (isset($value)) { /* Just testing we can output the var name without it being replaced. */ }

$something = ((array_merge(range("a", "z"), [1, 2, 3, 4, 5], $anotherVarName)) ?? (array (
)));

RESULT;

        $result = Blade::compileString('@test(array_merge(range("a", "z"), [1, 2, 3, 4, 5], $anotherVarName))');

        $this->assertSame($expected, $result);
    }
}
