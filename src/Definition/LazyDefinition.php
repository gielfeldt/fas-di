<?php

namespace Fas\DI\Definition;

use Fas\Autowire\Autowire;
use Fas\Autowire\CompiledClosure;
use Fas\DI\ProxyFactoryInterface;

class LazyDefinition implements DefinitionInterface
{
    private $className;
    private DefinitionInterface $definition;
    private ProxyFactoryInterface $proxyFactory;

    public function __construct(string $className, DefinitionInterface $definition, ProxyFactoryInterface $proxyFactory)
    {
        $this->className = $className;
        $this->definition = $definition;
        $this->proxyFactory = $proxyFactory;
    }

    public function make(Autowire $autowire)
    {
        // Proxy container entry
        $proxyMethod = function (&$wrappedObject, $proxy, $method, $parameters, &$initializer) use ($autowire) {
            $wrappedObject = $this->definition->make($autowire);
            $initializer   = null; // turning off further lazy initialization
        };

        return $this->proxyFactory->createProxy($this->className, $proxyMethod);
    }

    public function isCompilable(): bool
    {
        return $this->definition->isCompilable();
    }

    public function compile(Autowire $autowire): CompiledClosure
    {
        $factory = $this->definition->compile($autowire);

        $initializer = 'function (& $wrappedObject, $proxy, $method, $parameters, & $initializer) {
            $wrappedObject = ' . $factory . ';
            $initializer   = null;
        }';

        // Warning: assumes compiled container implements ProxyFactoryInterface
        $code = '$this->createProxy(' . var_export($this->className, true) . ', ' . $initializer . ')';
        return new CompiledClosure($code);
    }
}
