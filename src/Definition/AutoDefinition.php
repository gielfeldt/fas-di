<?php

namespace Fas\DI\Definition;

use Fas\Autowire\Autowire;
use Fas\Autowire\CompiledCode;
use Fas\Autowire\Exception\InvalidDefinitionException;

class AutoDefinition implements DefinitionInterface
{
    private DefinitionInterface $definition;

    public function __construct(string $id, $definition)
    {
        if ($definition instanceof DefinitionInterface) {
            $this->definition = $definition;
        } elseif ($id === $definition) {
            $this->definition = new ObjectDefinition($definition);
        } elseif (is_string($definition)) {
            $this->definition = new ReferenceDefinition($definition);
        } elseif (is_array($definition) || is_callable($definition)) {
            $this->definition = new FactoryDefinition($definition);
        } else {
            throw new InvalidDefinitionException($id, $definition);
        }
    }

    public function make(Autowire $autowire)
    {
        return $this->definition->make($autowire);
    }

    public function isCompilable(): bool
    {
        return $this->definition->isCompilable();
    }

    public function compile(Autowire $autowire): CompiledCode
    {
        return $this->definition->compile($autowire);
    }
}
