<?php

namespace Fas\DI;

use Fas\DI\Exception\CircularDependencyException;
use Fas\DI\Exception\InvalidDefinitionException;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

class Compiler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private array $definitions;
    private array $isLazy;
    private ContainerInterface $container;
    private Autowire $autowire;

    public $baseClass = '\\' . Container::class;

    public function __construct(array $definitions, array $isLazy, $container)
    {
        $this->definitions = $definitions;
        $this->isLazy = $isLazy;
        $this->autowire = new Autowire($container);
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
        $baseClass = $this->baseClass;
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
                return $this->autowire->compileNew($definition, null, $this->mayNeedResolving); // Class
            }
            if (is_string($definition)) {
                $this->mayNeedResolving[$definition] = true;
                return '$container->get(' . var_export($definition, true) . ')'; // Reference
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
        return '(' . $this->autowire->compileCall($definition, null, $this->mayNeedResolving) . ')([], $this)';
    }

    private function compileLazy(string $id)
    {
        $definition = $this->definitions[$id];
        if ($definition === $id) {
            // Proxy self
            $factory = $this->autowire->compileNew($definition, null, $this->mayNeedResolving);
        } elseif (is_string($definition)) {
            // Proxy container entry
            $this->mayNeedResolving[$definition] = true;
            $factory = '$container->get(' . var_export($definition, true) . ')';
        } elseif (is_callable($definition)) {
            // Proxy factory
            $factory = $this->compileFactory($definition);
        } else {
            throw new InvalidArgumentException("Cannot compile proxy for '$id'");
        }
        $proxyMethod = 'function (& $wrappedObject, $proxy, $method, $parameters, & $initializer) {
            $container = $this;
            $wrappedObject = ' . $factory . ';
            $initializer   = null;
        }';

        return "\$this->getProxyFactory()->createProxy(" . var_export($id, true) . ", $proxyMethod);";
    }
}
