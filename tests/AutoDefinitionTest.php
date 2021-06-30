<?php

namespace Fas\DI\Tests;

use BadMethodCallException;
use Fas\Autowire\Autowire;
use Fas\DI\Definition\AutoDefinition;
use Fas\DI\Definition\ValueDefinition;
use PHPUnit\Framework\TestCase;

class AutoDefinitionTest extends TestCase
{
    public function testCanPassThroughDefinition()
    {
        $def = new ValueDefinition('my-value');
        $auto = new AutoDefinition('test', $def);

        $value1 = $def->make(new Autowire());
        $value2 = $auto->make(new Autowire());
        $this->assertEquals($value1, $value2);
    }
}
