<?php

namespace Fas\DI;

use Closure;
use Fas\DI\Exception\CircularDependencyException;
use Fas\DI\Exception\InvalidDefinitionException;
use InvalidArgumentException;
use Opis\Closure\ReflectionClosure;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Throwable;

class Compiler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private array $definitions;
    private array $isLazy;
    private bool $deepResolve = true;

    public function __construct(array $definitions, array $isLazy)
    {
        $this->definitions = $definitions;
        $this->isLazy = $isLazy;
    }

    public function deepResolve(bool $deepResolve = true)
    {
        $this->deepResolve = $deepResolve;
        return $this;
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
                            $built[$id] = "return $compiled;";
                        }
                    } catch (Throwable $e) {
                        if ($this->logger) {
                            $this->logger->error($e->getMessage());
                        }
                    }
                }
            }
            if (!$this->deepResolve) {
                break;
            }
        }
        $factories = [];
        $factory_refs = [];
        $definitions = [];
        $ref = 1;
        foreach ($built as $id => $callback) {
            $factory_refs[$id] = "make_$ref";
            $factories["make_$ref"] = $callback;
            $definitions[$id] = true;
            $ref++;
        }

        $code = "<?php\n";
        $className = 'CompiledContainer' . uniqid();
        ob_start();
        include __DIR__ . '/CompiledContainer.php.tpl';
        $code .= ob_get_contents();
        ob_end_clean();

        $tmpfilename = tempnam(dirname($filename), 'container');
        file_put_contents($tmpfilename, $code);
        rename($tmpfilename, $filename);
        // chmod($filename, 0755);
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
                return $this->compileNew($definition); // Class
            }
            if (is_string($definition)) {
                $this->mayNeedResolving[$definition] = true;
                return '$this->get(' . var_export($definition, true) . ')'; // Reference
            }
            if (is_callable($definition)) {
                return $this->compileFactory($definition); // Factory
            }
            if ($definition === true) {
                if ($this->logger) {
                    $this->logger->warning("Skipping compile of value definition for '$id'");
                }
                return null;
            }
            throw new InvalidDefinitionException($id, $definition);
        } finally {
            unset($this->isCompiling[$id]);
        }
    }

    private function nameFromFunction(ReflectionFunctionAbstract $r)
    {
        $functionName = $r->getName();
        $className = $r->getClosureScopeClass();
        $className = $className ? $className->getName() : null;
        $functionName = $className ? "$className::$functionName" : $functionName;
        return $functionName;
    }

    private function compileArguments(ReflectionFunctionAbstract $r, $id)
    {
        $args = [];
        $ps = $r->getParameters();
        foreach ($ps as $p) {
            $name = $p->getName();
            $type = $p->hasType() ? $p->getType()->getName() : null;
            if (isset($type) && (isset($this->definitions[$type]) || class_exists($type))) {
                $this->mayNeedResolving[$type] = true;
                $args[] = '$this->get(' . var_export($type, true) . ')';
                continue;
            }
            if (!$p->isOptional() && class_exists($type)) {
                $args[] = '$this->get(' . var_export($type, true) . ')';
                continue;
            }
            if (!$p->isDefaultValueAvailable()) {
                $functionName = $this->nameFromFunction($r);
                throw new InvalidArgumentException("[$id] Argument: $functionName($type \$$name) has no default value while resolving");
            } elseif ($p->isPassedByReference()) {
                $functionName = $this->nameFromFunction($r);
                throw new InvalidArgumentException("[$id] Argument: $functionName($type \$$name) cannot autowire by reference parameters");
            } else {
                $args[] = var_export($p->getDefaultValue(), true);
            }
        }
        return $args;
    }

    private function compileNew(string $className)
    {
        if (!class_exists($className)) {
            throw new InvalidArgumentException("Class or service '$className' not found");
        }
        // No definition, assume class
        $c = new ReflectionClass($className);
        $r = $c->getConstructor();
        $args = $r ? $this->compileArguments($r, $className) : [];
        return "new \\" . $c->getName() . '(' . implode(',', $args) . ')';
    }

    private function compileFactory(callable $definition)
    {
        if (is_array($definition) && is_string($definition[0])) {
            [$class, $method] = $definition;
            $reflection = new ReflectionMethod($class, $method);
            $args = $this->compileArguments($reflection, $class);
            if (!$reflection->isStatic()) {
                $this->mayNeedResolving[$class] = true;
                return '$this->get(' . var_export($class, true) . ")->$method(" . implode(',', $args) . ')';
            } else {
                return "\\$class::$method(" . implode(',', $args) . ')';
            }
        }

        $id = (is_array($definition) && is_object($definition[0])) ? get_class($definition[0]) . '->' . $definition[1] : 'anonymous';

        $c = Closure::fromCallable($definition);
        $rf = new ReflectionClosure($c);
        $args = $this->compileArguments($rf, $id);
        return '(' . $rf->getCode() . ')(' . implode(',', $args) . ')';
    }

    private function compileLazy(string $id)
    {
        $definition = $this->definitions[$id];
        if ($definition === $id) {
            // Proxy self
            $factory = $this->compileNew($definition);
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
}
