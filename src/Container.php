<?php

declare(strict_types=1);

namespace Fas\DI;

use Closure;
use Exception;
use Fas\Autowire\Autowire;
use Fas\Autowire\CompiledClosure;
use Fas\Autowire\Exception\CircularDependencyException;
use Fas\Autowire\Exception\InvalidDefinitionException;
use Fas\Autowire\ReferenceTrackerInterface;
use Fas\Exportable\Exporter;
use Psr\Container\ContainerInterface;
use ProxyManager;

class Container implements ContainerInterface, ReferenceTrackerInterface
{
    protected array $definitions = [];
    protected array $resolving = [];
    protected array $resolved = [];
    protected array $factories = [];
    protected array $lazies = [];
    protected Autowire $autowire;
    protected array $references = [];
    protected $proxyFactory = null;
    public int $maxLevel = 10;

    public function __construct(?Autowire $autowire = null)
    {
        $this->autowire = $autowire ?? new Autowire($this);
        $this->resolved[ContainerInterface::class] = $this;
    }

    public function has(string $id): bool
    {
        return isset($this->definitions[$id]) || isset($this->resolved[$id]) || $this->autowire->canAutowire($id);
    }

    public function get(string $id)
    {
        return $this->resolved[$id] ?? (
            isset($this->factories[$id]) ? $this->make($id) : $this->resolved[$id] = $this->make($id)
        );
    }

    public function set(string $id, $definition = null)
    {
        $this->definitions[$id] = $definition ?? $id;
        return new Definition($id, $this->lazies, $this->factories);
    }

    public function singleton(string $id, $definition = null)
    {
        return $this->set($id, $definition);
    }

    public function lazy(string $id, $definition = null)
    {
        return $this->set($id, $definition)->lazy();
    }

    public function factory(string $id, $definition = null)
    {
        return $this->set($id, $definition)->factory();
    }

    public function isCompiled()
    {
        return false;
    }

    public function getAutowire()
    {
        return $this->autowire;
    }

    public function new(string $className, array $args = [])
    {
        return $this->autowire->new($className, $args);
    }

    public function call($callback, array $args = [])
    {
        return $this->autowire->call($callback, $args);
    }

    public function enableProxyCache(string $proxyCacheDirectory)
    {
        $config = new ProxyManager\Configuration();

        // Write proxies
        $fileLocator = new ProxyManager\FileLocator\FileLocator($proxyCacheDirectory);
        $config->setGeneratorStrategy(new ProxyManager\GeneratorStrategy\FileWriterGeneratorStrategy($fileLocator));

        $config->setProxiesTargetDir($proxyCacheDirectory);

        // Read proxies
        spl_autoload_register($config->getProxyAutoloader());

        $this->proxyFactory = new ProxyManager\Factory\LazyLoadingValueHolderFactory($config);
    }

    protected function make(string $id)
    {
        $this->mark($id);
        try {
            $definition = $this->definitions[$id] ?? $id;
            if ($id === $definition) {
                $constructor = function ($definition) {
                    return $this->autowire->new($definition);
                };
            } elseif (is_string($definition)) {
                $constructor = function ($definition) {
                    return $this->get($definition);
                };
            } elseif (is_callable($definition)) {
                $constructor = function ($definition) {
                    return $this->autowire->call($definition);
                };
            } else {
                throw new InvalidDefinitionException($id, var_export($definition, true));
            }
            if (isset($this->lazies[$id])) {
                $proxyMethod = function (&$wrappedObject, $proxy, $method, $parameters, &$initializer) use ($constructor, $definition) {
                    $wrappedObject = $constructor($definition);
                    $initializer   = null; // turning off further lazy initialization
                };
                return $this->createProxy($this->lazies[$id], $proxyMethod);
            } else {
                return $constructor($definition);
            }
        } finally {
            $this->unmark($id);
        }
    }

    protected function mark($id)
    {
        if (!empty($this->resolving[$id])) {
            throw new CircularDependencyException([...array_keys($this->resolving), $id]);
        }
        $this->resolving[$id] = $id;
    }

    protected function unmark($id)
    {
        unset($this->resolving[$id]);
    }

    protected function getProxyFactory()
    {
        if (!$this->proxyFactory) {
            $this->proxyFactory = new ProxyManager\Factory\LazyLoadingValueHolderFactory();
        }
        return $this->proxyFactory;
    }

    protected function createProxy(string $className, callable $initializer)
    {
        return $this->getProxyFactory()->createProxy($className, Closure::fromCallable($initializer));
    }

    public function trackReference(string $id)
    {
        $this->references[$id] = true;
    }

    public static function load(string $filename): ?Container
    {
        $loader = @include $filename;
        if (!$loader) {
            return null;
        }
        [$file, $class] = $loader;
        if (!class_exists($class, false)) {
            require_once $file;
        }
        return new $class();
    }

    public function save(string $filename, ?string $preload = null)
    {
        $this->autowire->setReferenceTracker($this);
        $methods = [];
        foreach ($this->definitions as $id => $definition) {
            if ($id == $definition) {
                $methods[$id] = $this->autowire->compileNew($definition);
            } elseif (is_string($definition)) {
                $this->trackReference($definition);
                $methods[$id] = new CompiledClosure('static function (\\' . ContainerInterface::class . ' $container, array $args = []) { return $container->get(' . var_export($definition, true) . '); }');
            } elseif (is_callable($definition)) {
                $methods[$id] = $this->autowire->compileCall($definition);
            } else {
                throw new InvalidDefinitionException($id, var_export($definition, true));
            }
            if (isset($this->lazies[$id])) {
                $this->get($id); // Attempt to trigger proxy cache generation
                $code = 'static function (\\' . ContainerInterface::class . ' $container, array $args = []) {
                $proxyMethod = function (&$wrappedObject, $proxy, $method, $parameters, &$initializer) use ($container) {
                    $wrappedObject = (' . $methods[$id] . ')($container);
                    $initializer   = null; // turning off further lazy initialization
                };
                return $container->createProxy(' . var_export($this->lazies[$id], true) . ', $proxyMethod);
                }';
                $methods[$id] = new CompiledClosure($code);
            }
        }

        $level = 0;
        while (!empty($this->references) && $level++ < $this->maxLevel) {
            $ids = array_diff(array_keys($this->references), array_keys($methods));
            $this->references = [];
            foreach ($ids as $id) {
                $methods[$id] = $this->autowire->compileNew($id);
            }
        }
        $id = hash('sha256', (new Exporter())->export($methods));
        $className = 'container_' . $id;
        $factories = $this->factories;
        $lazies = $this->lazies;
        $methodMap = [];
        foreach (array_keys($methods) as $i => $id) {
            $methodMap[$id] = 'make_' . $i;
        }
        ob_start();
        include __DIR__ . '/CompiledContainer.php.tpl';
        $code = "<?php\n" . ob_get_contents();
        ob_end_clean();

        $classFilename = dirname($filename) . '/' . $className . '.php';

        $tmpfilename = tempnam(dirname($classFilename), 'fas-container');
        @chmod($tmpfilename, 0666);
        file_put_contents($tmpfilename, $code);
        @chmod($tmpfilename, 0666);
        rename($tmpfilename, $classFilename);
        @chmod($classFilename, 0666);

        $tmpfilename = tempnam(dirname($filename), 'fas-container');
        @chmod($tmpfilename, 0666);
        file_put_contents($tmpfilename, '<?php return ' . var_export([realpath($classFilename), $className], true) . ';');
        @chmod($tmpfilename, 0666);
        rename($tmpfilename, $filename);
        @chmod($filename, 0666);

        if ($preload) {
            $this->savePreload($preload, $classFilename);
        }
        return array_keys($methods);
    }

    private function savePreload(string $filename, string $classFilename)
    {
        foreach (get_declared_classes() as $className) {
            if (strpos($className, 'ComposerAutoloader') === 0) {
                $classLoader = $className::getLoader();
                break;
            }
        }
        if (empty($classLoader)) {
            throw new Exception("Cannot locate class loader");
        }

        $files = [];
        $files[] = $classLoader->findFile(\Psr\Container\ContainerInterface::class);
        $files[] = $classLoader->findFile(\Fas\DI\Container::class);
        $files[] = $classLoader->findFile(ProxyManager\Configuration::class);
        $files[] = $classLoader->findFile(ProxyManager\FileLocator\FileLocator::class);
        $files[] = $classLoader->findFile(ProxyManager\GeneratorStrategy\FileWriterGeneratorStrategy::class);
        $files[] = $classLoader->findFile(ProxyManager\Autoloader\Autoloader::class);
        $files[] = $classLoader->findFile(ProxyManager\Inflector\ClassNameInflector::class);
        $files[] = $classLoader->findFile(ProxyManager\ProxyGenerator\LazyLoadingValueHolderGenerator::class);
        $files[] = $classLoader->findFile(ProxyManager\Factory\LazyLoadingValueHolderFactory::class);

        $files[] = $classFilename;

        $preload = "<?php\n";
        foreach ($files as $file) {
            $preload .= 'require_once(' . var_export(realpath($file), true) . ");\n";
        }

        $tempfile = tempnam(dirname($filename), 'fas-container');
        @chmod($tempfile, 0666);
        file_put_contents($tempfile, $preload);
        @chmod($tempfile, 0666);
        rename($tempfile, $filename);
        @chmod($filename, 0666);
    }
}
