<?php

namespace Fas\DI\Tests;

class TestImplementationDep
{
    private TestImplementation $test;

    public function __construct(TestImplementation $test)
    {
        $this->test = $test;
    }
}
