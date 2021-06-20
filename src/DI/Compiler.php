<?php

declare(strict_types=1);

namespace Fas\DI;

use Closure;
use Fas\DI\Exception\CircularDependencyException;
use Fas\DI\Exception\InvalidDefinitionException;
use Fas\DI\Exception\NoDefaultValueException;
use Fas\DI\Exception\NotFoundException;
use InvalidArgumentException;
use Opis\Closure\ReflectionClosure;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Throwable;

class Compiler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private array $definitions;
    private array $isLazy;
    private ContainerInterface $container;

    public $baseClass = '\\' . Container::class;

    public function __construct(array $definitions, array $isLazy, $container)
    {
        $this->definitions = $definitions;
        $this->isLazy = $isLazy;
        $this->container = $container;
    }

    public function compile(string $filename)
    {
        $this->isCompiling = [];
        $this->mayNeedResolving = [];

        $resolved = [];

        $this->mayNeedResolving = $this->definitions;

        $built = [];
        while (!empty($this->mayNeedResolving)) {
            $mayNeedResolving = array_keys($this->mayNeedResolving);
            $this->mayNeedResolving = [];
            foreach ($mayNeedResolving as $id) {
                if (!isset($resolved[$id])) {
                    $resolved[$id] = true;
                    try {
                        if ($compiled = $this->compileEntry($id)) {
                            $built[$id] = $compiled;
                        }
                    } catch (Throwable $e) {
                        if ($this->logger) {
                            $this->logger->error($e->getMessage());
                        }
                    }
                }
            }
        }
        $factories = [];
        $definitions = [];
        foreach ($built as $id => $callback) {
            $factories[$id] = $callback;
            $definitions[$id] = true;
        }

        $code = "<?php\n";
        $className = 'CompiledContainer' . uniqid();
        $baseClass = $this->baseClass;
        ob_start();
        include __DIR__ . '/CompiledContainer.php.tpl';
        $code .= ob_get_contents();
        ob_end_clean();

        $tmpfilename = tempnam(dirname($filename), 'container');
        @chmod($tmpfilename, 0666);
        file_put_contents($tmpfilename, $code);
        @chmod($tmpfilename, 0666);
        rename($tmpfilename, $filename);
        @chmod($filename, 0666);
        return array_keys($built);
    }

    private function compileEntry(string $id)
    {
        if (isset($this->isCompiling[$id])) {
            throw new CircularDependencyException([...array_keys($this->isCompiling), $id]);
        }
        try {
            $this->isCompiling[$id] = true;

            if (isset($this->isLazy[$id])) {
                return $this->compileLazy($id);
            }
            $definition = $this->definitions[$id] ?? $id;
            if ($id === $definition) {
                return $this->compileNew($definition, null, $this->mayNeedResolving); // Class
            }
            if (is_string($definition)) {
                $this->mayNeedResolving[$definition] = true;
                return '$this->get(' . var_export($definition, true) . ')'; // Reference
            }
            if (is_callable($definition)) {
                return $this->compileFactory($definition); // Factory
            }
            throw new InvalidDefinitionException($id, $definition);
        } finally {
            unset($this->isCompiling[$id]);
        }
    }

    private function compileFactory(callable $definition)
    {
        return $this->compileCall($definition, null, $this->mayNeedResolving);
    }

    private function compileLazy(string $id)
    {
        $definition = $this->definitions[$id];
        if ($definition === $id) {
            // Proxy self
            $factory = $this->compileNew($definition, null, $this->mayNeedResolving);
        } elseif (is_string($definition)) {
            // Proxy container entry
            $this->mayNeedResolving[$definition] = true;
            $factory = '$this->get(' . var_export($definition, true) . ')';
        } elseif (is_callable($definition)) {
            // Proxy factory
            $factory = $this->compileFactory($definition);
        } else {
            throw new InvalidArgumentException("Cannot compile proxy for '$id'");
        }
        $proxyMethod = 'function (& $wrappedObject, $proxy, $method, $parameters, & $initializer) {
            $wrappedObject = ' . $factory . ';
            $initializer   = null;
        }';

        return "\$this->getProxyFactory()->createProxy(" . var_export($id, true) . ", $proxyMethod);";
    }

    // COMPILE
    public function compileCall($callback, ?array $defaultArgs = [], array &$mayNeedResolving = [])
    {
        $reflection = null;
        if (is_array($callback)) {
            [$class, $method] = $callback;
            if (is_object($class)) {
                throw new InvalidArgumentException("Cannot compile instantiated object");
            }
            if ($this->container && $this->container->has($class)) {
                $instance = $this->container->get($class);
                $class = get_class($instance);
            }

            $reflection = new ReflectionMethod($class, $method);
            $args = $this->compileArguments($reflection, $defaultArgs, $mayNeedResolving);
            if ($reflection->isStatic()) {
                return $class . '::' . $method . '(' . implode(', ', $args) . ')';
            } elseif (!$this->container) {
                return '(' . $this->compileNew($class, null, $mayNeedResolving) . ')->' . $method . '(' . implode(', ', $args) . ')';
            } else {
                return '($this->get(' . var_export($class, true) . '))->' . $method . '(' . implode(', ', $args) . ')';
            }
        }

        if (is_string($callback) && !$this->container && class_exists($callback)) {
            $instance = $this->container->get($callback);
            if (!is_callable($instance)) {
                throw new InvalidDefinitionException($callback, $callback);
            }
            $reflection = new ReflectionMethod($instance, '__invoke');
            $args = $this->compileArguments($reflection, $defaultArgs, $mayNeedResolving);
            return '(' . $this->compileNew(get_class($instance), $defaultArgs, $mayNeedResolving) . ')(' . implode(', ', $args) . ')';
        } elseif (is_string($callback) && $this->container && $this->container->has($callback)) {
            $instance = $this->container->get($callback);
            if (!is_callable($instance)) {
                throw new InvalidDefinitionException($callback, $callback);
            }
            $reflection = new ReflectionMethod($instance, '__invoke');
            $args = $this->compileArguments($reflection, $defaultArgs, $mayNeedResolving);
            return '($this->get(' . var_export($callback, true) . '))(' . implode(', ', $args) . ')';
        }

        $closure = Closure::fromCallable($callback);
        $reflection = new ReflectionFunction($closure);
        $args = $this->compileArguments($reflection, $defaultArgs, $mayNeedResolving);
        $rf = new ReflectionClosure($closure);
        $staticVariables = [];
        foreach ($rf->getStaticVariables() as $key => $value) {
            $staticVariables[] = "\$$key = " . var_export($value, true) . ';';
        }
        $code = $rf->getCode();

        if (!empty($staticVariables)) {
            $staticVariables = implode("\n", $staticVariables);
            return '
(static function () {
    ' . $staticVariables . '
    return (' . $code . ')(' . implode(', ', $args) . ');
})()';
        } else {
            if ($rf->getNumberOfParameters() === 0) {
                #$code = preg_replace('/(.*?){(.*)}$/s', '$2', $code);
                #return $code;
            }
            return '(' . $code . ')(' . implode(', ', $args) . ')';
        }
    }

    public function compileNew(string $className, ?array $defaultArgs = null, array &$mayNeedResolving = [])
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
            $args = $r ? $this->compileArguments($r, $defaultArgs, $mayNeedResolving) : [];
            return "new \\" . $c->getName() . '(' . implode(', ', $args) . ')';
        } finally {
            unset($this->resolving[$className]);
        }
    }

    private function compileArguments(ReflectionFunctionAbstract $r, ?array $defaultArgs = null, array &$mayNeedResolving = [])
    {
        $args = [];
        $ps = $r->getParameters();
        foreach ($ps as $p) {
            $name = $p->getName();
            $type = $p->hasType() ? $p->getType()->getName() : null;
            $parg = $pparg = "";
            if ($defaultArgs !== null) {
                $parg = '$args[' . var_export($name, true) . ']';
                $pparg = "$parg ?? ";
            }
            if ($defaultArgs && array_key_exists($name, $defaultArgs)) {
                if ($p->isVariadic()) {
                    $args[$name] = '...' . $pparg . var_export($defaultArgs[$name], true);
                } else {
                    $args[$name] = $pparg . var_export($defaultArgs[$name], true);
                }
                continue;
            }
            if (isset($type) && !$this->container && class_exists($type)) {
                $args[$name] = $pparg . $this->compileNew($type, $defaultArgs, $mayNeedResolving);
                continue;
            }
            if (isset($type) && $this->container && $this->container->has($type)) {
                $mayNeedResolving[$type] = true;
                $args[$name] = $pparg . '$this->get(' . var_export($type, true) . ')';
                continue;
            }
            if ($p->isDefaultValueAvailable()) {
                $args[$name] = $pparg . var_export($p->getDefaultValue(), true);
                continue;
            }
            if ($p->isOptional()) {
                $args[$name] = 'null';
                continue;
            }
            $functionName = $this->nameFromFunction($r);
            $source = "$functionName($type \$$name)";
            $args[$name] = $pparg . '\\' . Compiler::class . '::noDefaultValueAvailable(' . var_export($source, true) . ')';
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
}
