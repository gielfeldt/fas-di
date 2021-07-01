<?php

namespace Fas\DI\Definition;

use Fas\Autowire\Autowire;
use Fas\Autowire\CompiledCode;

class ReferenceDefinition implements DefinitionInterface
{
    private string $reference;

    public function __construct(string $reference)
    {
        $this->reference = $reference;
    }

    public function make(Autowire $autowire)
    {
        return $autowire->getContainer()->get($this->reference);
    }

    public function isCompilable(): bool
    {
        return true;
    }

    public function compile(Autowire $autowire): CompiledCode
    {
        $autowire->trackReference($this->reference);
        return new CompiledCode('$this->get(' . var_export($this->reference, true) . ')');
    }
}
