<?php

namespace Fas\DI\Tests;

use BadMethodCallException;
use Fas\Autowire\Autowire;
use Fas\DI\Definition\ValueDefinition;
use PHPUnit\Framework\TestCase;

class ValueDefinitionTest extends TestCase
{
    public function testCanMakeValue()
    {
        $def = new ValueDefinition('my-value');

        $value = $def->make(new Autowire());
        $this->assertEquals('my-value', $value);
    }

    public function testValueIsNotCompilable()
    {
        $def = new ValueDefinition('my-value');
        $this->assertFalse($def->isCompilable());
    }

    public function testThrowExceptionOnCompile()
    {
        $def = new ValueDefinition('my-value');
        $this->expectException(BadMethodCallException::class);
        $def->compile(new Autowire());
    }
}
