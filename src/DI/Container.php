<?php

namespace Fas\DI;

use Fas\DI\Exception\CircularDependencyException;
use Fas\DI\Exception\InvalidDefinitionException;
use ProxyManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Simple container with proxies and compilation.
 */
class Container implements ContainerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected array $definitions = [];
    protected array $resolved = [];
    protected array $isLazy = [];
    protected array $isResolving = [];
    protected $proxyFactory = null;
    protected $autoloader = null;

    public function __construct()
    {
        $this->resolved[Container::class] = $this;
        $this->resolved[ContainerInterface::class] = $this;
        $this->definitions[Container::class] = true;
        $this->definitions[ContainerInterface::class] = true;

        $this->autowire = new Autowire($this);
    }

    /**
     * Load a compiled container
     */
    public static function load(string $filename): ?Container
    {
        $container = @include $filename;
        return $container instanceof Container ? $container : null;
    }

    public function save(string $filename)
    {
        return $this->compiler()->compile($filename);
    }

    // ----- PSR -----
    /**
     * @inheritdoc
     */
    public function has(string $id): bool
    {
        return isset($this->definitions[$id]) || class_exists($id);
    }

    /**
     * @inheritdoc
     */
    public function get(string $id)
    {
        return $this->resolved[$id] ?? $this->resolved[$id] = $this->make($id);
    }

    // ----- CONFIGURATION -----
    public function singleton(string $id, $definition = null)
    {
        $this->definitions[$id] = $definition ?? $id;
    }

    public function lazy(string $id, $definition = null)
    {
        $this->isLazy[$id] = true;
        $this->definitions[$id] = $definition ?? $id;
    }

    public function enableProxyCache(string $proxyCacheDirectory)
    {
        $config = new ProxyManager\Configuration();

        // Write proxies
        $fileLocator = new ProxyManager\FileLocator\FileLocator($proxyCacheDirectory);
        $config->setGeneratorStrategy(new ProxyManager\GeneratorStrategy\FileWriterGeneratorStrategy($fileLocator));

        // Read proxies
        $config->setProxiesTargetDir($proxyCacheDirectory);
        $this->registerAutoloader($config->getProxyAutoloader());

        $this->proxyFactory = new ProxyManager\Factory\LazyLoadingValueHolderFactory($config);
    }

    public function useProxyCache(string $proxyCacheDirectory)
    {
        $config = new ProxyManager\Configuration();

        // Read proxies
        $config->setProxiesTargetDir($proxyCacheDirectory);
        $this->registerAutoloader($config->getProxyAutoloader());

        $this->proxyFactory = new ProxyManager\Factory\LazyLoadingValueHolderFactory($config);
    }

    // ----- BUILDING -----
    public function buildProxyCache(string $proxyCacheDirectory)
    {
        // Fresh resolved for lazy object
        foreach (array_keys($this->isLazy) as $id) {
            unset($this->resolved[$id]);
        }
        $this->isResolving = [];

        $autoloader = $this->autoloader;
        $this->unregisterAutoloader();

        $config = new ProxyManager\Configuration();

        // Write proxies
        $fileLocator = new ProxyManager\FileLocator\FileLocator($proxyCacheDirectory);
        $config->setGeneratorStrategy(new ProxyManager\GeneratorStrategy\FileWriterGeneratorStrategy($fileLocator));

        // Read proxies
        $config->setProxiesTargetDir($proxyCacheDirectory);

        $proxyFactory = $this->proxyFactory;
        $this->proxyFactory = new ForcedLazyLoadingValueHolderFactory($config);

        $built = [];
        foreach (array_keys($this->isLazy) as $id) {
            $built[] = $id;
            $this->make($id);
        }

        // then register the autoloader
        if ($autoloader) {
            $this->registerAutoloader($autoloader);
        }
        $this->proxyFactory = $proxyFactory;

        return $built;
    }

    public function compiler(): Compiler
    {
        return new Compiler($this->definitions, $this->isLazy, $this);
    }

    // ----- USAGE -----
    protected function make(string $id)
    {
        if (isset($this->isResolving[$id])) {
            throw new CircularDependencyException([...array_keys($this->isResolving), $id]);
        }
        try {
            $this->isResolving[$id] = true;
            if ($this->logger) {
                $this->logger->debug("Resolving: $id");
            }

            if (isset($this->isLazy[$id])) {
                return $this->makeLazy($id);
            }
            $definition = $this->definitions[$id] ?? $id;
            if ($id === $definition) {
                return $this->autowire->new($definition); // Class
            }
            if (is_string($definition)) {
                return $this->get($definition); // Reference
            }
            if (is_callable($definition)) {
                return $this->autowire->call($definition); // Factory
            }
            throw new InvalidDefinitionException($id, $definition);
        } finally {
            unset($this->isResolving[$id]);
        }
    }

    // ---- RESOLVING ----
    private function makeLazy(string $id)
    {
        $definition = $this->definitions[$id];
        if ($definition === $id) {
            // Proxy self
            $proxyMethod = function (&$wrappedObject, $proxy, $method, $parameters, &$initializer) use ($definition) {
                $wrappedObject = $this->autowire->new($definition);
                $initializer   = null; // turning off further lazy initialization
            };
        } elseif (is_string($definition)) {
            // Proxy container entry
            $proxyMethod = function (&$wrappedObject, $proxy, $method, $parameters, &$initializer) use ($definition) {
                $wrappedObject = $this->get($definition);
                $initializer   = null; // turning off further lazy initialization
            };
        } else {
            // Proxy factory
            $proxyMethod = function (&$wrappedObject, $proxy, $method, $parameters, &$initializer) use ($definition) {
                $wrappedObject = $this->autowire->call($definition);
                $initializer   = null; // turning off further lazy initialization
            };
        }
        return $this->getProxyFactory()->createProxy($id, $proxyMethod);
    }

    // ---- PROXY ----
    protected function getProxyFactory()
    {
        if (!$this->proxyFactory) {
            $this->proxyFactory = new ProxyManager\Factory\LazyLoadingValueHolderFactory();
        }
        return $this->proxyFactory;
    }

    private function registerAutoloader(callable $autoloader)
    {
        $this->unregisterAutoloader();
        spl_autoload_register($autoloader);
        $this->autoloader = $autoloader;
    }

    private function unregisterAutoloader()
    {
        if ($this->autoloader) {
            spl_autoload_unregister($this->autoloader);
        }
    }
}
