# Blade Directives

This library provides utility methods that aim to make writing Laravel Blade directives easier, especially when attempting to use multiple parameters.

This library makes it possible to write code like this:

```php
<?php

use Stillat\BladeDirectives\Support\Facades\Directive;

Directive::compile('slugify', function ($value, $separator = '-') {
    return '<?php echo \Illuminate\Support\Str::slug($value, $separator); ?>';
});
```

Which allows your users to write Blade like this:

```blade
@slugify($title)
@slugify($title, '_')
```

Developers using directives written using the `compile` method can also use named arguments:

```php
<?php

use Stillat\BladeDirectives\Support\Facades\Directive;

Directive::compile('limit', function ($value, $limit = 100, $end = '...') {
   return '<?php echo \Illuminate\Support\Str::limit($value, $limit, $end); ?>';
});
```

```blade
@limit($myString, end: '---')
@limit($myString, end: ':o', limit: 5)
```

That just feels better, and we didn't have to resort to error-prone `explode` calls, or `json_encode/json_decode`.

## Installation

This library can be installed with Composer:

```
composer require stillat/blade-directives
```

## Writing Directives

To get started, add the following import to the top of your PHP file:

```php
use Stillat\BladeDirectives\Support\Facades\Directive;
```

The `Directive` façade provides three different methods that you can choose from depending on what you require for a given situation:

* `params($name, callable $handler)` - registers a new Blade directive similarly to Blade's default `directive` method. You will have access to the parameters within the callback through `$this->parameters`. You will only receive the raw input string as the first, and only argument.
* `make($name, callable $handler)` - registers a new Blade directive with support for multiple parameters
* `compile($name, callable $handler)` - registers a new Blade directive with support for multiple parameters. This method allows you to return a PHP string, which will have variables replaced for you automatically

### Using the `make($name, callable $handler)` Method

The `make` method allows you to specify (and receive) multiple parameters on your directive's callback. You are still responsible for manually constructing the final PHP string for the Blade compiler:

```php
<?php

use Stillat\BladeDirectives\Support\Facades\Directive;

Directive::make('greet', function ($name, $age) {
   return '<?php echo "Hello, ".'.$name.". ' - '. ".$age."; ?>";
});
```

The following Blade:

```blade
@greet('Hello!', 32)
@greet($varName, $ageVar)
@greet(' Escaped \' string!', '32')
```

Produces the following compiled output:

```php
<?php echo "Hello, ".'Hello!'. ' - '. 32; ?>
<?php echo "Hello, ".$varName. ' - '. $ageVar; ?>
<?php echo "Hello, ".' Escaped \' string!'. ' - '. '32'; ?>
```

### Using the `compile($name, callable $handler)` Method

The `compile` method allows you to return a PHP string as the result of your directive's handler. The internal compiler will work to replace the variable references with the required compiled output, which makes it much easier for you to write your directive.

Let's rewrite the `greet` directive using the `compile` method:

```php
<?php

use Stillat\BladeDirectives\Support\Facades\Directive;

Directive::compile('greet', function ($name, $age) {
   return '<?php echo "Hello, $name - $age" ?>';
});
```

The following example demonstrates using default values for directive parameters:

```php
<?php

use Illuminate\Support\Facades\Blade;
use Stillat\BladeDirectives\Support\Facades\Directive;

Directive::compile('test', function ($data = [1, 2, 3]) {
   return '<?php foreach ($data as $value): ?>';
});

Blade::directive('endtest', function () {
    return '<?php endforeach; ?>';
});
```

The following Blade:

```blade
@test(['one', 'two', 'three'])

@endtest
```

Produces compiled output similar to the following:

```php
<?php foreach (((['one', 'two', 'three']) ?? (array (
  0 => 1,
  1 => 2,
  2 => 3,
))) as $value): ?>

<?php endforeach; ?>
```

If the user did not supply any values like so:

```blade
@test

@endtest
```

The compiled output would change to:

```php
<?php foreach (((null) ?? (array (
  0 => 1,
  1 => 2,
  2 => 3,
))) as $value): ?>

<?php endforeach; ?>
```

## Escaping Variable Names

This section applies to the `compile` method.

The "compiler" is effectively a glorified "find and replace", and can make it difficult to use a variable name if it also matches one of your parameter names. To escape a variable name, precede it with the `\` character:

```php
<?php

use Stillat\BladeDirectives\Support\Facades\Directive;

Directive::compile('escapeTest', function ($test, $anotherVar) {
   return '<?php echo 5 + ($test) / $anotherVar; ?> \$test \$anotherVar $anotherVar';
});
```

The following Blade:

```blade
@escapeTest(5 + 3 * 2, 15)
```

Produces compiled output similar to the following:

```php
<?php echo 5 + (5 + 3 * 2) / 15; ?> $test $anotherVar 15
```

As you can see, anything that matches a variable name preceded by the `\` character is escaped for you. Additionally, all occurrences of the variable are replaced in the string, not just within PHP areas.

## Issues

Due to the nature of projects like this, it is guaranteed that it will break in interesting ways. When submitting issues, please include as much information as possible to help reproduce the issue, and a clear explanation of desired behavior.

## License

MIT License. See LICENSE.MD
