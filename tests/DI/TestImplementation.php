<?php

namespace Fas\DI\Tests;

class TestImplementation implements TestInterface
{
    private $id;

    public function __construct($id = 'default')
    {
        $this->id = $id;
    }

    public function id()
    {
        return $this->id;
    }

    public function implementation($name = 'abc')
    {
        return strtoupper($name);
    }

    public function hasdefaultparameters($name = 'abc')
    {
        return strtoupper($name);
    }

    public function nodefaultparameters($name)
    {
        return strtoupper($name);
    }

    public static function staticfunction($name = 'abc')
    {
        return strtoupper($name);
    }

    public function __invoke($name = 'abc')
    {
        return strtoupper($name);
    }
}
