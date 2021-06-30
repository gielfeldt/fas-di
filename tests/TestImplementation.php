<?php

namespace Fas\DI\Tests;

class TestImplementation implements TestInterface
{
    public static $counter = 0;
    private $id;

    public function __construct($id = null)
    {
        self::$counter++;
        $this->id = $id ?? uniqid();
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
