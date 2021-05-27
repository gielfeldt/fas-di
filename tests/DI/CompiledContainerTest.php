<?php

namespace Fas\DI\Tests;

use Fas\DI\Container;
use Fas\DI\Tests\TestImplementation;
use PHPUnit\Framework\TestCase;

class CompiledContainerTest extends TestCase
{
    public function testCanCreateLazyProxy()
    {
        $container = new Container();

        TestImplementation::$counter = 0;

        $container->lazy(TestImplementation::class);

        $filename = tempnam(sys_get_temp_dir(), 'fas-routing-test');
        $container->save($filename);
        $container = Container::load($filename);
        unlink($filename);

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

        $filename = tempnam(sys_get_temp_dir(), 'fas-routing-test');
        $container->save($filename);
        $container = Container::load($filename);
        unlink($filename);

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

        $filename = tempnam(sys_get_temp_dir(), 'fas-routing-test');
        $container->save($filename);
        $container = Container::load($filename);
        unlink($filename);

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

        $filename = tempnam(sys_get_temp_dir(), 'fas-routing-test');
        $container->save($filename);
        $container = Container::load($filename);
        unlink($filename);

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
        $container->singleton(Circular1::class);
        $container->singleton(TestImplementation::class, function () {
            return new TestImplementation();
        });

        $filename = tempnam(sys_get_temp_dir(), 'fas-routing-test');
        $container->save($filename);
        $container = Container::load($filename);
        unlink($filename);

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

        $filename = tempnam(sys_get_temp_dir(), 'fas-routing-test');
        $container->save($filename);
        $container = Container::load($filename);
        unlink($filename);

        $test = $container->get(TestInterface::class);

        $this->assertEquals(1, TestImplementation::$counter);

        $this->assertEquals("ABC", $test->implementation('abc'));

        $this->assertEquals(1, TestImplementation::$counter);

    }


    public function testCanBuildProxies()
    {
        $container = new Container();

        TestImplementation::$counter = 0;

        $proxydir = tempnam(sys_get_temp_dir(), 'fas-di-proxy');
        unlink($proxydir);
        mkdir($proxydir);

        $container->lazy(TestInterface::class, function () {
            return new TestImplementation();
        });
        $container->buildProxyCache($proxydir);

        $test = $container->get(TestInterface::class);

        $this->assertEquals(0, TestImplementation::$counter);

        $this->assertEquals("ABC", $test->implementation('abc'));

        $this->assertEquals(1, TestImplementation::$counter);

    }

}
