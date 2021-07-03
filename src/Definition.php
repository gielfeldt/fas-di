<?php

namespace Fas\DI;

class Definition
{
    private string $id;
    private array $lazies;
    private array $factories;

    public function __construct(string $id, array &$lazies, array &$factories)
    {
        $this->id = $id;
        $this->lazies = &$lazies;
        $this->factories = &$factories;
    }

    public function lazy(?string $className = null)
    {
        $this->lazies[$this->id] = $className ?? $this->id;
        return $this;
    }

    public function factory(bool $factory = true)
    {
        $this->factories[$this->id] = $factory;
        return $this;
    }
}
