<?php

namespace Fas\DI;

interface ProxyFactoryInterface
{
    public function createProxy(string $className, callable $initializer);
}
