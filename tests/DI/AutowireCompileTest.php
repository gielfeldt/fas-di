<?php

namespace Fas\DI\Tests;

use Fas\DI\Autowire;
use Fas\DI\Exception\CircularDependencyException;
use Fas\DI\Exception\NoDefaultValueException;
use Fas\DI\Exception\NotFoundException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AutowireCompileTest extends TestCase
{

    protected Autowire $autowire;

    public function setup(): void
    {
        $this->autowire = new Autowire();
    }

    private function callCompiled($callback, $args = [])
    {
        $code = $this->autowire->compileCall($callback);
        eval("\$closure = $code;");
        return $closure($args);
    }

    private function newCompiled($className)
    {
        $code = $this->autowire->compileNew($className);
        eval("\$closure = $code;");
        return $closure($args);
    }

    public function testCanCallCompiledClosureWithDefaultParameters()
    {
        $result = $this->callCompiled(static function ($name = 'abc') {
            return strtoupper($name);
        });

        $this->assertEquals("ABC", $result);
    }

    public function testCanCallClosureWithNamedParameter()
    {
        $result = $this->callCompiled(static function ($name = 'abc') {
            return strtoupper($name);
        }, ['name' => 'cba']);

        $this->assertEquals("CBA", $result);
    }

    public function testCanUseScopeVariablesInClosure()
    {
        $scopedVar = '123';
        $result = $this->callCompiled(static function ($name = 'abc') use ($scopedVar) {
            return strtoupper($name) . $scopedVar;
        }, ['name' => 'cba']);

        $this->assertEquals("CBA123", $result);
    }

    public function testWillFailWhenClosureHasNoDefaultParameters()
    {
        $this->expectException(NoDefaultValueException::class);

        $result = $this->callCompiled(static function ($name) {
            return strtoupper($name);
        });
    }

    public function testCanCallClassWithDefaultParameters()
    {
        $result = $this->callCompiled([TestImplementation::class, 'hasdefaultparameters']);

        $this->assertEquals("ABC", $result);
    }

    public function testCanCallClassWithNamedParameter()
    {
        $result = $this->callCompiled([TestImplementation::class, 'hasdefaultparameters'], ['name' => 'cba']);

        $this->assertEquals("CBA", $result);
    }

    public function testWillFailWhenMethodHasNoDefaultParameters()
    {
        $this->expectException(NoDefaultValueException::class);

        $this->callCompiled([TestImplementation::class, 'nodefaultparameters']);
    }

    public function testCanCallInvokableClassWithDefaultParameters()
    {
        $result = $this->callCompiled(TestImplementation::class);

        $this->assertEquals("ABC", $result);
    }

    public function testCanCallStaticClassMethodWithDefaultParameters()
    {
        $result = $this->callCompiled([TestImplementation::class, 'staticfunction']);

        $this->assertEquals("ABC", $result);
    }

    public function testCanHandleCircularReferences()
    {
        $this->expectException(CircularDependencyException::class);

        $this->newCompiled(Circular1::class);
    }

    public function testCanHandleCircularReferencesFromCall()
    {
        $this->expectException(CircularDependencyException::class);

        $this->callCompiled([Circular1::class, 'test']);
    }

    public function testCanHandleNonExistingClass()
    {
        $this->expectException(NotFoundException::class);

        $this->newCompiled('someclassthatdoesnotexist');
    }

    public function testCanCompileWithParameters()
    {
        $callback = static function ($name = 'abc') {
            return strtoupper($name);
        };

        $code = $this->autowire->compileCall($callback, ['name' => 'cba']);
        eval("\$closure = $code;");
        $result = $closure();

        $this->assertEquals("CBA", $result);
    }

    public function testCannotCompileInstantiatedObject()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->callCompiled([new TestImplementation(), 'implementation']);
    }

}
