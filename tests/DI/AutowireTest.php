<?php

namespace Fas\DI\Tests;

use Fas\DI\Autowire;
use Fas\DI\Exception\CircularDependencyException;
use Fas\DI\Exception\NoDefaultValueException;
use Fas\DI\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class AutowireTest extends TestCase
{

    protected Autowire $autowire;

    public function setup(): void
    {
        $this->autowire = new Autowire();
    }

    public function testCanCallClosureWithDefaultParameters()
    {
        $result = $this->autowire->call(static function ($name = 'abc') {
            return strtoupper($name);
        });

        $this->assertEquals("ABC", $result);
    }

    public function testCanCallClosureWithNamedParameter()
    {
        $result = $this->autowire->call(static function ($name = 'abc') {
            return strtoupper($name);
        }, ['name' => 'cba']);

        $this->assertEquals("CBA", $result);
    }

    public function testWillFailWhenClosureHasNoDefaultParameters()
    {
        $this->expectException(NoDefaultValueException::class);

        $this->autowire->call(static function ($name) {
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

    public function testCanCallWithVariadicParameters()
    {
        $result = $this->autowire->call(static function (TestImplementation $test, ...$args) {
            return $test->implementation(join(',', $args));
        }, [
            'args' => ['c','b','a']
        ]);

        $this->assertEquals("C,B,A", $result);
    }

    public function testCanCallUsingByReferenceParameters()
    {
        $args = ['c','b','a'];
        $result = $this->autowire->call(static function (TestImplementation $test, array &$args) {
            $r = $test->implementation(join(',', $args));
            $args = [1, 2, 3];
            return $r;
        }, [
            'args' => &$args,
        ]);

        $this->assertEquals("C,B,A", $result);
        $this->assertEquals([1,2,3], $args);
    }

}
