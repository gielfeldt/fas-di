<?php

namespace Fas\DI\Tests;

use Fas\DI\Autowire;
use Fas\DI\Container;
use Fas\DI\Exception\CircularDependencyException;
use Fas\DI\Exception\NoDefaultValueException;
use Fas\DI\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class AutowireContainerTest extends TestCase
{
    protected Container $container;

    public function setup(): void
    {
        $this->container = new Container;
        $this->autowire = new Autowire($this->container);
        $this->container->singleton(TestInterface::class, TestImplementation::class);
        $this->container->singleton(TestImplementation::class);
    }

    public function testCanCallClosureWithDefaultParameters()
    {
        $result = $this->autowire->call(function ($name = 'abc') {
            return strtoupper($name);
        });

        $this->assertEquals("ABC", $result);
    }

    public function testCanCallClosureWithNamedParameter()
    {
        $result = $this->autowire->call(function ($name = 'abc') {
            return strtoupper($name);
        }, ['name' => 'cba']);

        $this->assertEquals("CBA", $result);
    }

    public function testWillFailWhenClosureHasNoDefaultParameters()
    {
        $this->expectException(NoDefaultValueException::class);

        $this->autowire->call(function ($name) {
            return strtoupper($name);
        });
    }

    public function testCanCallClassWithDefaultParameters()
    {
        $result = $this->autowire->call([TestImplementation::class, 'hasdefaultparameters']);

        $this->assertEquals("ABC", $result);
    }

    public function testCanCallClassWithNamedParameter()
    {
        $result = $this->autowire->call([TestImplementation::class, 'hasdefaultparameters'], ['name' => 'cba']);

        $this->assertEquals("CBA", $result);
    }

    public function testWillFailWhenMethodHasNoDefaultParameters()
    {
        $this->expectException(NoDefaultValueException::class);

        $this->autowire->call([TestImplementation::class, 'nodefaultparameters']);
    }

    public function testCanCallInvokableClassWithDefaultParameters()
    {
        $result = $this->autowire->call(TestImplementation::class);

        $this->assertEquals("ABC", $result);
    }

    public function testCanCallStaticClassMethodWithDefaultParameters()
    {
        $result = $this->autowire->call([TestImplementation::class, 'staticfunction']);

        $this->assertEquals("ABC", $result);
    }

    public function testCanHandleCircularReferences()
    {
        $this->expectException(CircularDependencyException::class);

        $this->autowire->new(Circular1::class);
    }

    public function testCanHandleCircularReferencesFromCall()
    {
        $this->expectException(CircularDependencyException::class);

        $this->autowire->call([Circular1::class, 'test']);
    }

    public function testCanHandleNonExistingClass()
    {
        $this->expectException(NotFoundException::class);

        $this->autowire->new('someclassthatdoesnotexist');
    }

    public function testResolveInterfaceViaTypeHint()
    {
        $result = $this->autowire->call(function (TestInterface $test) {
            return $test->implementation('cba');
        });

        $this->assertEquals("CBA", $result);
    }

    public function testCanCallWithVariadicParameters()
    {
        $result = $this->autowire->call(function (TestInterface $test, ...$args) {
            return $test->implementation(join(',', $args));
        }, [
            'args' => ['c','b','a']
        ]);

        $this->assertEquals("C,B,A", $result);
    }

}
