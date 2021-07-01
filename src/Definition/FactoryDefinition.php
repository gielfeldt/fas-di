<?php

namespace Fas\DI\Definition;

use Fas\Autowire\Autowire;
use Fas\Autowire\CompiledCode;

class FactoryDefinition implements DefinitionInterface
{
    private $factory;

    public function __construct($factory)
    {
        $this->factory = $factory;
    }

    public function make(Autowire $autowire)
    {
        return $autowire->call($this->factory);
    }

    public function isCompilable(): bool
    {
        return true;
    }

    public function compile(Autowire $autowire): CompiledCode
    {
        $code = $autowire->compileCall($this->factory);
        return new CompiledCode('(' . $code . ')($this)');
    }
}
