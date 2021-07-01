<?php

namespace Fas\DI\Definition;

use BadMethodCallException;
use Fas\Autowire\Autowire;
use Fas\Autowire\CompiledCode;

class ValueDefinition implements DefinitionInterface
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function make(Autowire $autowire)
    {
        return $this->value;
    }

    public function isCompilable(): bool
    {
        return false;
    }

    public function compile(Autowire $autowire): CompiledCode
    {
        throw new BadMethodCallException("Cannot compile value");
    }
}
