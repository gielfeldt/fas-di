<?php

namespace Fas\DI;

use Closure;
use Fas\DI\Exception\CircularDependencyException;
use Fas\DI\Exception\InvalidDefinitionException;
use Fas\DI\Exception\NoDefaultValueException;
use Fas\DI\Exception\NotFoundException;
use InvalidArgumentException;
use Opis\Closure\ReflectionClosure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

class Autowire implements ContainerInterface
{
    private ?ContainerInterface $container;
    private $resolving = [];

    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container ?? $this;
    }

    public function has(string $id): bool
    {
        return class_exists($id);
    }

    public function get(string $id)
    {
        return $this->new($id);
    }

    // CALL
    public function call($callback, array $namedargs = [])
    {
        if (is_array($callback) && is_string($callback[0])) {
            [$class, $method] = $callback;
            $reflection = new ReflectionMethod($class, $method);
            if (!$reflection->isStatic()) {
                $instance = $this->container->get($class);
                $reflection = new ReflectionMethod($instance, $method);
                $args = $this->createArguments($reflection, $namedargs, $class);
                return $reflection->invokeArgs($instance, $args);
            }
        }
        if (is_string($callback) && $this->container->has($callback)) {
            $callback = $this->container->get($callback);
        }

        $closure = Closure::fromCallable($callback);
        $r = new ReflectionFunction($closure);
        $args = $this->createArguments($r, $namedargs);
        return $r->invokeArgs($args);
    }

    public function new(string $className, array $namedargs = [])
    {
        if (!class_exists($className)) {
            throw new NotFoundException($className);
        }

        if (isset($this->resolving[$className])) {
            throw new CircularDependencyException([...array_keys($this->resolving), $className]);
        }
        try {
            $this->resolving[$className] = true;
            $c = new ReflectionClass($className);
            $r = $c->getConstructor();
            $args = $r ? $this->createArguments($r, $namedargs, $className) : [];
            return $c->newInstanceArgs($args);
        } finally {
            unset($this->resolving[$className]);
        }
    }



    public function compileMethodCall($class, $method, $namedargs)
    {
        if ($this->container && $this->container->has($class)) {
            $instance = $this->container->get($class);
            $class = get_class($instance);
        }

        $reflection = new ReflectionMethod($class, $method);
        $args = $this->compileArguments($reflection, $namedargs);
        if ($reflection->isStatic()) {
            return '
static function ($args = [], ?\\' . ContainerInterface::class . ' $container = null) {
    return \\' . $class . '::' . $method . '(' . implode(', ', $args) . ');
}';
        } elseif ($this->container === $this) {
            return '
static function ($args = [], ?\\' . ContainerInterface::class . ' $container = null) {
    $instance = ' . $this->compileNew($class) . ';
    return $instance->' . $method . '(' . implode(', ', $args) . ');
}';
        } else {
            return '
static function ($args = [], ?\\' . ContainerInterface::class . ' $container = null) {
    $instance = $container->get(' . var_export($class, true) . ');
    return $instance->' . $method . '(' . implode(', ', $args) . ');
}';
        }
    }

    // COMPILE
    public function compileCall($callback, array $namedargs = [])
    {
        $reflection = null;
        if (is_array($callback)) {
            [$class, $method] = $callback;
            if (is_object($class)) {
                throw new InvalidArgumentException("Cannot compile instantiated object");
            }
            return $this->compileMethodCall($class, $method, $namedargs);
        }

        if (is_string($callback) && $this->container === $this && $this->container->has($callback)) {
            $instance = $this->container->get($callback);
            if (!is_callable($instance)) {
                throw new InvalidDefinitionException($callback, $callback);
            }
            $reflection = new ReflectionMethod($instance, '__invoke');
            $args = $this->compileArguments($reflection, $namedargs);
            return '
static function ($args = [], ?\\' . ContainerInterface::class . ' $container = null) {
    $instance = ' . $this->compileNew(get_class($instance)) . ';
    return $instance(' . implode(', ', $args) . ');
}';
        } elseif (is_string($callback) && $this->container->has($callback)) {
            $instance = $this->container->get($callback);
            if (!is_callable($instance)) {
                throw new InvalidDefinitionException($callback, $callback);
            }
            $reflection = new ReflectionMethod($instance, '__invoke');
            $args = $this->compileArguments($reflection, $namedargs);
            return '
static function ($args = [], ?\\' . ContainerInterface::class . ' $container = null) {
    $instance = $container->get(' . var_export($callback, true) . ');
    return $instance(' . implode(', ', $args) . ');
}';
        }

        $closure = Closure::fromCallable($callback);
        $reflection = new ReflectionFunction($closure);
        $args = $this->compileArguments($reflection, $namedargs);
        $rf = new ReflectionClosure($closure);
        $staticVariables = [];
        foreach ($rf->getStaticVariables() as $key => $value) {
            $staticVariables[] = "\$$key = " . var_export($value, true) . ';';
        }
        $staticVariables = implode("\n", $staticVariables);
        return '
static function ($args = [], ?\\' . ContainerInterface::class . ' $container = null) {
    ' . $staticVariables . '
    return (' . $rf->getCode() . ')(' . implode(', ', $args) . ');
}';
    }

    private function createArguments(ReflectionFunctionAbstract $r, $namedargs = [], $id = null)
    {
        $args = [];
        $ps = $r->getParameters();
        foreach ($ps as $p) {
            $name = $p->getName();
            $type = $p->hasType() ? $p->getType()->getName() : null;
            // Named parameters first
            if (array_key_exists($name, $namedargs)) {
                if ($p->isVariadic()) {
                    array_push($args, ...$namedargs[$name]);
                } elseif ($p->isPassedByReference()) {
                    $args[] = &$namedargs[$name];
                } else {
                    $args[] = $namedargs[$name];
                }
                continue;
            }
            if (isset($type) && $this->container->has($type)) {
                $args[] = $this->container->get($type);
                continue;
            }
            if (!$p->isDefaultValueAvailable()) {
                $functionName = $this->nameFromFunction($r);
                throw new NoDefaultValueException("[$id] Argument: $functionName($type \$$name) has no default value while resolving");
            } elseif (!$p->isPassedByReference()) {
                $args[] = $p->getDefaultValue();
            }
        }
        return $args;
    }


    private function nameFromFunction(ReflectionFunctionAbstract $r)
    {
        $functionName = $r->getName();
        $className = $r->getClosureScopeClass();
        $className = $className ? $className->getName() : null;
        $functionName = $className ? "$className::$functionName" : $functionName;
        return $functionName;
    }

    public static function noDefaultValueAvailable($source)
    {
        throw new NoDefaultValueException("Argument: $source has no default value while resolving");
    }

    private function compileArguments(ReflectionFunctionAbstract $r, $namedargs = [])
    {
        $args = [];
        $ps = $r->getParameters();
        foreach ($ps as $p) {
            $name = $p->getName();
            $type = $p->hasType() ? $p->getType()->getName() : null;
            $parg = '$args[' . var_export($name, true) . ']';
            $pparg = "$parg ?? ";
            if (array_key_exists($name, $namedargs)) {
                $args[$name] = $pparg . var_export($namedargs[$name], true);
                continue;
            }
            if (isset($type) && $this->container === $this && $this->container->has($type)) {
                $args[$name] = $pparg . $this->compileNew($type);
                continue;
            }
            if (isset($type) && $this->container->has($type)) {
                $args[$name] = $pparg . '$container->get(' . var_export($type, true) . ')';
                continue;
            }
            if (!$p->isDefaultValueAvailable()) {
                $functionName = $this->nameFromFunction($r);
                $source = "$functionName($type \$$name)";
                $args[$name] = $pparg . '\\' . Autowire::class . '::noDefaultValueAvailable(' . var_export($source, true) . ')';
            } else {
                $args[$name] = $pparg . var_export($p->getDefaultValue(), true);
            }
        }
        return $args;
    }

    public function compileNew(string $className, $namedargs = [])
    {
        if (!class_exists($className)) {
            throw new NotFoundException($className);
        }
        if (isset($this->resolving[$className])) {
            throw new CircularDependencyException([...array_keys($this->resolving), $className]);
        }
        try {
            $this->resolving[$className] = true;
            $c = new ReflectionClass($className);
            $r = $c->getConstructor();
            $args = $r ? $this->compileArguments($r, $namedargs) : [];
            return "new \\" . $c->getName() . '(' . implode(', ', $args) . ')';
        } finally {
            unset($this->resolving[$className]);
        }
    }
}
