 

# Usage

## Creating container

```php
// Brand new container
$container = new Container;

// Load compiled container if present
$container = Container::load('/tmp/container.php');
```

## Adding dependencies

Dependencies are resolved once, and then re-used subsequently.

```php
// ->singleton(entryName, entryName | callback)
$container->singleton(LoggerInterface::class, 'some_container_entry');

 // If entry MyLogger::class does not exist in the container,
 // The class MyLogger::class will be instantiated.
$container->singleton(LoggerInterface::class, MyLogger::class);

// Custom factory method
$container->singleton(LoggerInterface::class, function () {
    return new MyLogger;
});

// Any callable will do
$container->singleton(LoggerInterface::class, [MyLoggerFactory::class, 'create']);
```

Lazy. Will not be resolved before used. (virtual proxy)

API is identical to ->singleton(), except you can omit 2nd parameter in order to turn any entry/class lazy

```php
// ->lazy(entryName, entryName | callback | null)
$container->lazy(MyLogger::class); // Lazy shorthand
```

## Performance
```php

// Use cached virtual proxies (lazy), and write cache if missing
$container->enableProxyCache('/tmp/proxies');

// Use cached virtual proxies (lazy) if present, but don't write them
$container->useProxyCache('/tmp/proxies');

// Build cache for all virual proxies.
$container->buildProxyCache('/tmp/proxies');

// Generate a class containing proper methods for registered entries.
// This can be used afterwards to avoid a lot of reflection when resolving entries.
$container->compile('/tmp/container.php');
```

# Recipies

## Full automatic cached container and proxies

The easist way to make use of caching.

Be aware, that once a cache has been written, it has to be manually deleted in order to be renewed. This setup is usually not very useful in development.

```php
$container = Container::load("/tmp/container.php");
if (!$container) {
    $container = new Container;
    $container->singleton(LoggerInterface::class, MyLogger::class);
    $container->compile('/tmp/container.php');
}
$container->enableProxyCache("/tmp/proxies");

// Container ready for use
```

## Use cache if present

If cache is generated during startup or build phase, it can be sufficient to just use
the cache if it's present.

```php
$container = Container::load("/tmp/container.php");
if (!$container) {
    $container = new Container;
    $container->singleton(LoggerInterface::class, MyLogger::class);
}
$container->useProxyCache("/tmp/proxies");

// Container ready for use
```

## Write all cache

This can be used in a compile script during startup of build phase.

```php
$container = new Container;

// ... populate container here

$proxies = $container->buildProxyCache("/tmp/proxies");
$entries = $container->compile("/tmp/container.php");

print "-----------------\nBuilt " . count($proxies) . " proxies\n-----------------\n";
print implode("\n", $proxies) . "\n\n";

print "-----------------\nCompiled " . count($entries) . " entries\n-----------------\n";
print implode("\n", $entries) . "\n\n";

```

## Generic example
Generic example using configuration, container and router.

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use App\ContainerFactory;
use App\RouterFactory;
use Fas\DI\Container;
use Fas\Configuration\DotNotation;
use Fas\Configuration\YamlLoader;
use Fas\Routing\Router;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;

$configFile = getenv('CONFIG_FILE') ?: '/app/config.yaml';

try {
    // Setup configuration, container and router
    $configuration = FileCache::load('/tmp/config.cache.php') ?? new DotNotation(YamlLoader::loadWithOverrides($configFile));
    $container = Container::load('/tmp/container.cache.php') ?? ContainerFactory::create($configuration);
    $router = Router::load('/tmp/router.cache.php') ?? RouterFactory::create($container);

    // Handle actual request
    $request = ServerRequestFactory::fromGlobals();
    $response = $router->handle($request);
} catch (Throwable $e) {
    $code = $e instanceof HttpException ? $e->getCode() : 500;
    $response = (new ResponseFactory)->createResponse($code, $e->getMessage());
    $response->getBody()->write('<pre>' . (string) $e . '</pre>');
} finally {
    (new SapiEmitter)->emit($response);
}

```

## Generic compiler example
Compiler example for the example above

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use App\ContainerFactory;
use App\RouterFactory;
use Fas\DI\Container;
use Fas\Configuration\DotNotation;
use Fas\Configuration\YamlLoader;
use Fas\Routing\Router;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;

$configFile = getenv('CONFIG_FILE') ?: '/app/config.yaml';

// Compile config
$configuration = new DotNotation(YamlLoader::loadWithOverrides($configFile));
FileCache::save('/tmp/config.cache.php', $configuration);

// Compile container
$container = ContainerFactory::create($configuration);
$proxies = $container->buildProxyCache('/tmp/proxy.cache');
print "-----------------\nBuilt " . count($proxies) . " proxies\n-----------------\n" . implode("\n", $proxies) . "\n-----------------\n";
$entries = $container->save('/tmp/container.cache.php');
print "-----------------\nBuilt " . count($entries) . " entries\n-----------------\n" . implode("\n", $entries) . "\n-----------------\n";

// Compile routes
$router = RouterFactory::create($container);
$router->save('/tmp/router.cache.php');
```

# Autowiring

The container uses the included Autowire class for performing autowiring.

This functionality can be used without the container (or with another container) if desired.

## Without container

Without a container, only concrete classes can be autowired, and only if they
have concrete dependences in their constructor.

```php
<?php

use App\MyClass;
use Fas\DI\Autowire;

$autowire = new Autowire;

$myfunction = static function (MyClass $myclass, $name = 'test') {
    return $myclass->someMethodThatUppercasesAString("Hello: $name");
};

$result = $autowire->call($myfunction, ['name' => 'autowire with named parameter']);

// => HELLO: AUTOWIRE WITH NAMED PARAMETER
print "$result\n";
```


## With container

With a container, the container will be used for resolving function arguments.

```php
<?php

use App\MyClass;
use App\MyClassInterface;
use Fas\DI\Autowire;
use Fas\DI\Container;

$container = new Container;
$container->singleton(MyClassInterface::class, MyClass::class);
$autowire = new Autowire($container);

$myfunction = static function (MyClassInterface $myclass, $name = 'test') {
    return $myclass->someMethodThatUppercasesAString("Hello: $name");
};

$result = $autowire->call($myfunction, ['name' => 'autowire with named parameter']);

// => HELLO: AUTOWIRE WITH NAMED PARAMETER
print "$result\n";
```
