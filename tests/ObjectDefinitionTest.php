<?php

namespace Fas\DI\Tests;

use Fas\Autowire\Autowire;
use Fas\Autowire\CompiledCode;
use Fas\Autowire\Exception\NotFoundException;
use Fas\DI\Definition\ObjectDefinition;
use Fas\DI\Tests\TestImplementation;
use PHPUnit\Framework\TestCase;

class ObjectDefinitionTest extends TestCase
{
    public function testCanMakeClass()
    {
        $def = new ObjectDefinition(TestImplementation::class);

        $obj = $def->make(new Autowire());
        $this->assertInstanceOf(TestImplementation::class, $obj);
    }

    public function testWillThrowExceptionOnNonClass()
    {
        $this->expectException(NotFoundException::class);
        $def = new ObjectDefinition('test');
    }

    public function testCanCompileClass()
    {
        $def = new ObjectDefinition(TestImplementation::class);

        $autowire = new Autowire();
        $code = $def->compile($autowire);
        $this->assertTrue($code instanceof CompiledCode);
    }
}
