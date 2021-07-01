<?php

namespace Fas\DI\Tests;

use Fas\Autowire\Exception\CircularDependencyException;
use Fas\Autowire\Exception\InvalidDefinitionException;
use Fas\DI\Container;
use Fas\DI\Tests\TestImplementation;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    public function testIsCompiledFlag()
    {
        $container = new Container();
        $this->assertFalse($container->isCompiled());
    }

    public function testCanCreateLazyProxy()
    {
        $container = new Container();

        TestImplementation::$counter = 0;

        $container->lazy(TestImplementation::class);

        $test = $container->get(TestImplementation::class);

        $this->assertEquals(0, TestImplementation::$counter);
        $this->assertEquals("ABC", $test->implementation('abc'));
        $this->assertEquals(1, TestImplementation::$counter);
    }

    public function testCanCreateLazyProxyMapping()
    {
        $container = new Container();

        TestImplementation::$counter = 0;

        $container->lazy(TestInterface::class, TestImplementation::class);

        $test = $container->get(TestInterface::class);

        $this->assertEquals(0, TestImplementation::$counter);

        $this->assertEquals("ABC", $test->implementation('abc'));

        $this->assertEquals(1, TestImplementation::$counter);
    }

    public function testCanCreateLazyProxyFactory()
    {
        $container = new Container();

        TestImplementation::$counter = 0;

        $container->lazy(TestInterface::class, function () {
            return new TestImplementation();
        });

        $test = $container->get(TestInterface::class);

        $this->assertEquals(0, TestImplementation::$counter);

        $this->assertEquals("ABC", $test->implementation('abc'));

        $this->assertEquals(1, TestImplementation::$counter);
    }

    public function testCanCreateSingleton()
    {
        $container = new Container();

        TestImplementation::$counter = 0;

        $container->singleton(TestImplementation::class);

        $test = $container->get(TestImplementation::class);

        $this->assertEquals(1, TestImplementation::$counter);

        $this->assertEquals("ABC", $test->implementation('abc'));

        $this->assertEquals(1, TestImplementation::$counter);
    }

    public function testCanCreateSingletonMapping()
    {
        $container = new Container();

        TestImplementation::$counter = 0;

        $container->singleton(TestInterface::class, TestImplementation::class);

        $test = $container->get(TestInterface::class);

        $this->assertEquals(1, TestImplementation::$counter);

        $this->assertEquals("ABC", $test->implementation('abc'));

        $this->assertEquals(1, TestImplementation::$counter);
    }

    public function testCanCreateSingletonFactory()
    {
        $container = new Container();

        TestImplementation::$counter = 0;

        $container->singleton(TestInterface::class, function () {
            return new TestImplementation();
        });

        $test = $container->get(TestInterface::class);

        $this->assertEquals(1, TestImplementation::$counter);

        $this->assertEquals("ABC", $test->implementation('abc'));

        $this->assertEquals(1, TestImplementation::$counter);
    }

    public function testCanDetectInvalidDefinitions()
    {
        $this->expectException(InvalidDefinitionException::class);

        $container = new Container();
        $container->singleton('invaliddefinition', 1234);

        $container->get('invaliddefinition');
    }

    public function testCircularDependencyWillFail()
    {
        $container = new Container();
        $container->singleton('test1', 'test2');
        $container->singleton('test2', 'test3');
        $container->singleton('test3', 'test1');

        $this->expectException(CircularDependencyException::class);
        $container->get('test1');
    }

    public function testCanCreateAbstractFactory()
    {
        $container = new Container();
        $container->factory(TestImplementation::class);

        $this->assertNotEquals($container->get(TestImplementation::class)->id(), $container->get(TestImplementation::class)->id());
        $this->assertNotEquals($container->get(TestImplementation::class)->id(), $container->get(TestImplementation::class)->id());
    }

    public function testCanUseAutowire()
    {
        $container = new Container();
        $container->singleton('test', TestImplementation::class);
        $id = $container->get('test')->id();

        $result = $container->getAutowire()->call(['test', 'id']);
        $this->assertEquals($id, $result);
    }

    public function testCanCreateLazyProxyMappingWithCache()
    {
        $container = new Container();
        $container->enableProxyCache('/tmp/');

        TestImplementation::$counter = 0;

        $container->lazy(TestInterface::class, TestImplementation::class);

        $test = $container->get(TestInterface::class);

        $this->assertEquals(0, TestImplementation::$counter);
        $this->assertEquals("ABC", $test->implementation('abc'));
        $this->assertEquals(1, TestImplementation::$counter);
        var_dump(`ls -la /tmp/`);
        $found = count(glob('/tmp/ProxyManagerGeneratedProxy__PM__FasDITestsTestInterfaceGenerated*.php')) > 0;
        $this->assertTrue($found);
    }
}
