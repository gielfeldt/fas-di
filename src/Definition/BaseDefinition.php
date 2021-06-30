<?php

namespace Fas\DI\Definition;

use Fas\Autowire\Autowire;
use Fas\Autowire\CompiledClosure;
use Fas\DI\ProxyFactoryInterface;

class BaseDefinition implements DefinitionInterface
{
    private string $id;
    private DefinitionInterface $definition;
    private array $singletons;
    private bool $abstractFactory = false;
    private ?string $lazyClassName = null;
    private $resolved = null;
    private ProxyFactoryInterface $proxyFactory;

    public function __construct(string $id, DefinitionInterface $definition, array &$singletons, ProxyFactoryInterface $proxyFactory)
    {
        $this->singletons = &$singletons;
        $this->definition = $definition;
        $this->id = $id;
        $this->proxyFactory = $proxyFactory;
    }

    public function singleton()
    {
        $this->lazyClassName = null;
        return $this;
    }

    public function lazy(string $className = null)
    {
        $this->lazyClassName = $className ?? $this->id;
        return $this;
    }

    public function factory(bool $abstractFactory = true)
    {
        $this->abstractFactory = $abstractFactory;
        return $this;
    }

    private function resolve()
    {
        $definition = $this->definition;
        $definition = $this->lazyClassName ? new LazyDefinition($this->lazyClassName, $definition, $this->proxyFactory) : $definition;

        if (!$this->abstractFactory) {
            $definition = new SingletonDefinition($this->id, $definition, $this->singletons);
        }
        return $definition;
    }

    public function make(Autowire $autowire)
    {
        $definition = $this->resolved ?? $this->resolved = $this->resolve();
        return $definition->make($autowire);
    }

    public function isCompilable(): bool
    {
        $definition = $this->resolved ?? $this->resolved = $this->resolve();
        return $definition->isCompilable();
    }

    public function compile(Autowire $autowire): CompiledClosure
    {
        $definition = $this->resolved ?? $this->resolved = $this->resolve();
        return $definition->compile($autowire);
    }
}
