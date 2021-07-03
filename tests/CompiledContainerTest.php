<?php

namespace Fas\DI\Tests;

use Fas\Autowire\Exception\InvalidDefinitionException;
use Fas\DI\Container;
use Fas\DI\Tests\TestImplementation;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CompiledContainerTest extends TestCase
{

    public function testIsCompiledFlag()
    {
        $container = new Container();

        $filename = tempnam(sys_get_temp_dir(), 'fas-di-test');
        $container->save($filename);
        $container = Container::load($filename);
        unlink($filename);

        $this->assertTrue($container->isCompiled());
    }

    public function testCanCreateLazyProxy()
    {
        $container = new Container();

        TestImplementation::$counter = 0;

        $container->lazy(TestImplementation::class);

        $filename = tempnam(sys_get_temp_dir(), 'fas-di-test');
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

        $filename = tempnam(sys_get_temp_dir(), 'fas-di-test');
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

        $filename = tempnam(sys_get_temp_dir(), 'fas-di-test');
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

        $filename = tempnam(sys_get_temp_dir(), 'fas-di-test');
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
        $container->singleton(TestImplementation::class, function () {
            return new TestImplementation();
        });

        $filename = tempnam(sys_get_temp_dir(), 'fas-di-test');
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

        $filename = tempnam(sys_get_temp_dir(), 'fas-di-test');
        $container->save($filename);
        $container = Container::load($filename);
        unlink($filename);

        $test = $container->get(TestInterface::class);

        $this->assertEquals(1, TestImplementation::$counter);

        $this->assertEquals("ABC", $test->implementation('abc'));

        $this->assertEquals(1, TestImplementation::$counter);
    }

    public function testCanDetectInvalidDefinition()
    {
        $this->expectException(InvalidDefinitionException::class);

        $container = new Container();
        $container->singleton('invaliddefinition', 1234);


        $filename = tempnam(sys_get_temp_dir(), 'fas-di-test');
        $container->save($filename);
        unlink($filename);
    }

    public function testCanDetectInvalidLazyDefinition()
    {
        $this->expectException(InvalidDefinitionException::class);

        $container = new Container();
        $container->lazy('invaliddefinition', 1234);

        $filename = tempnam(sys_get_temp_dir(), 'fas-di-test');
        $container->save($filename);
        unlink($filename);
    }

    public function testReferenceTracking()
    {
        $container = new Container();
        $container->singleton('test', TestImplementationDep::class);

        $filename = tempnam(sys_get_temp_dir(), 'fas-di-test');
        $container->save($filename);
        $container = Container::load($filename);
        unlink($filename);

        $this->assertArrayHasKey(TestImplementation::class, $container::METHODS);
    }
}
