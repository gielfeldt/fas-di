<?php

namespace Fas\DI\Tests;

use Psr\Container\ContainerInterface;

class ContainerDep
{
    private TestImplementation $test;

    public function __construct(ContainerInterface $container, TestImplementation $test)
    {
        $this->test = $test;
        $this->name = $container->get('name', 'nothing');
    }

    public function result()
    {
        return $this->test->implementation($this->name);
    }
}
