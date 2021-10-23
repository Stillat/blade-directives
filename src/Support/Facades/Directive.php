<?php

namespace Stillat\BladeDirectives\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Stillat\BladeDirectives\DirectiveFactory;

/**
 * @method static void make(string $name, callable $handler)
 * @method static void compile(string $name, callable $handler)
 *
 * @see \Stillat\BladeDirectives\DirectiveFactory
 */
class Directive extends Facade
{
    protected static function getFacadeAccessor()
    {
        return DirectiveFactory::class;
    }
}
