<?php

namespace Fas\DI\Definition;

use Fas\Autowire\Autowire;
use Fas\Autowire\CompiledClosure;

interface DefinitionInterface
{
    public function make(Autowire $autowire);
    public function isCompilable(): bool;
    public function compile(Autowire $autowire): CompiledClosure;
}
