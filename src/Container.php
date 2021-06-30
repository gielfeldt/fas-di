<?php

declare(strict_types=1);

namespace Fas\DI;

use Closure;
use Fas\Autowire\Autowire;
use Fas\DI\Definition\AutoDefinition;
use Fas\DI\Definition\BaseDefinition;
use Fas\DI\Definition\DefinitionInterface;
use Fas\DI\Definition\ObjectDefinition;
use Fas\DI\Definition\SingletonDefinition;
use Fas\DI\Definition\ValueDefinition;
use Fas\Autowire\Exception\CircularDependencyException;
use Fas\Autowire\ReferenceTrackerInterface;
use ProxyManager;
use Psr\Container\ContainerInterface;

/**
 * Simple container with proxies and compilation.
 */
class Container implements ContainerInterface, ReferenceTrackerInterface, ProxyFactoryInterface
{
    protected array $definitions = [];
    protected array $resolved = [];
    protected array $isResolving = [];
    protected $proxyFactory = null;
    protected $autoloader = null;
    protected Autowire $autowire;

    public function __construct()
    {
        $this->resolved[Container::class] = $this;
        $this->resolved[ContainerInterface::class] = $this;
        $this->definitions[ContainerInterface::class] = $this->definitions[Container::class] = new ValueDefinition($this);
        $this->autowire = new Autowire($this);
        $this->autowire->setReferenceTracker($this);
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
        return $this->resolved[$id] ?? $this->make($id);
    }

    // ----- Building -----
    public function set(string $id, DefinitionInterface $definition)
    {
        return $this->definitions[$id] = new BaseDefinition($id, $definition, $this->resolved, $this);
    }

    public function auto(string $id, $definition = null)
    {
        return $this->set($id, new AutoDefinition($id, $definition ?? $id));
    }

    public function singleton(string $id, $definition = null)
    {
        return $this->auto($id, $definition)->singleton();
    }

    public function lazy(string $id, $definition = null)
    {
        return $this->auto($id, $definition)->lazy();
    }

    public function factory(string $id, $definition = null)
    {
        return $this->auto($id, $definition)->factory();
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
        $built = [];
        $this->references = [];

        foreach ($this->definitions as $id => $definition) {
            if ($definition->isCompilable()) {
                $built[$id] = $definition->compile($this->autowire);
            }
        }

        $references = $this->references;
        while ($references) {
            $this->references = [];
            foreach ($references as $id) {
                if (isset($built[$id])) {
                    continue;
                }
                $built[$id] = (new SingletonDefinition($id, new ObjectDefinition($id), $this->resolved))->compile($this->autowire);
            }
            $references = $this->references;
        }

        $factories = [];
        $factory_refs = [];
        $definitions = [];
        $ref = 1;
        foreach ($built as $id => $callback) {
            $factory_refs[$id] = "make_$ref";
            $factories["make_$ref"] = "return $callback;";
            $definitions[$id] = true;
            $ref++;
        }

        $code = "<?php\n";
        $className = 'CompiledContainer' . uniqid();
        #$baseClass = $this->baseClass;
        $baseClass = '\\' . self::class;
        //$isLazy = $this->isLazy;
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

    public function isCompiled(): bool
    {
        return false;
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
        $definition = $this->definitions[$id] ?? new BaseDefinition($id, new ObjectDefinition($id), $this->resolved, $this);
        try {
            $this->markResolving($id);
            return $definition->make($this->autowire);
        } finally {
            $this->unmarkResolving($id);
        }
    }

    public function trackReference(string $id)
    {
        $this->references[] = $id;
    }

    protected function assertCircular($id)
    {
        if (!empty($this->isResolving[$id])) {
            throw new CircularDependencyException([...array_keys($this->isResolving), $id]);
        }
    }

    public function markResolving(string ...$ids)
    {
        foreach ($ids as $id) {
            $this->assertCircular($id);
            $this->isResolving[$id] = true;
        }
    }

    public function unmarkResolving(string ...$ids)
    {
        foreach ($ids as $id) {
            unset($this->isResolving[$id]);
        }
    }

    protected function getProxyFactory()
    {
        if (!$this->proxyFactory) {
            $this->proxyFactory = new ProxyManager\Factory\LazyLoadingValueHolderFactory();
        }
        return $this->proxyFactory;
    }

    public function createProxy(string $className, callable $initializer)
    {
        return $this->getProxyFactory()->createProxy($className, Closure::fromCallable($initializer));
    }

    public function getAutowire()
    {
        return $this->autowire;
    }
}
