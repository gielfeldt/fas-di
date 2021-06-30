<?php

namespace Fas\DI\Definition;

use Fas\Autowire\Autowire;
use Fas\Autowire\CompiledClosure;
use Fas\Autowire\Exception\NotFoundException;

class ObjectDefinition implements DefinitionInterface
{
    private string $className;

    public function __construct(string $className)
    {
        if (!class_exists($className)) {
            throw new NotFoundException($className);
        }
        $this->className = $className;
    }

    public function make(Autowire $autowire)
    {
        return $autowire->new($this->className);
    }

    public function isCompilable(): bool
    {
        return true;
    }

    public function compile(Autowire $autowire): CompiledClosure
    {
        $code = $autowire->compileNew($this->className);
        return new CompiledClosure('(' . $code . ')($this)');
    }
}
