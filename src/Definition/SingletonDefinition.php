<?php

namespace Fas\DI\Definition;

use Fas\Autowire\Autowire;
use Fas\Autowire\CompiledClosure;

class SingletonDefinition implements DefinitionInterface
{
    private string $id;
    private DefinitionInterface $definition;
    private array $singletons;

    public function __construct(string $id, DefinitionInterface $definition, array &$singletons)
    {
        $this->singletons = &$singletons;
        $this->definition = $definition;
        $this->id = $id;
    }

    public function make(Autowire $autowire)
    {
        return $this->singletons[$this->id] = $this->definition->make($autowire);
    }

    public function isCompilable(): bool
    {
        return $this->definition->isCompilable();
    }

    public function compile(Autowire $autowire): CompiledClosure
    {
        return new CompiledClosure('$this->resolved[' . var_export($this->id, true) . '] = ' . $this->definition->compile($autowire));
    }
}
