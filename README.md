[![Build Status](https://github.com/gielfeldt/fas-di/actions/workflows/test.yml/badge.svg)][4]
![Test Coverage](https://img.shields.io/endpoint?url=https://gist.githubusercontent.com/gielfeldt/0a1cf78da65d9c91d05e6a5ef1ec0808/raw/fas-di__main.json)

[![Latest Stable Version](https://poser.pugx.org/fas/di/v/stable.svg)][1]
[![Latest Unstable Version](https://poser.pugx.org/fas/di/v/unstable.svg)][2]
[![License](https://poser.pugx.org/fas/di/license.svg)][3]
![Total Downloads](https://poser.pugx.org/fas/di/downloads.svg)


# Installation

```bash
composer require fas/di
```


# Usage

## Creating container

```php
// Brand new container
$container = new Container;

// Load compiled container if present
$container = Container::load('/tmp/container.php');
```

## Adding dependencies

```php
// ->set(entryName, entryName | callback | null)
// (singleton by default)
$container->set(LoggerInterface::class, 'some_container_entry');

// abstract factory
$container->set(LoggerInterface::class, 'some_container_entry')->factory();

// lazy
$container->set(LoggerInterface::class, 'some_container_entry')->lazy(LoggerInterface::class);

// lazy abstract factory
$container->set(LoggerInterface::class, 'some_container_entry')
    ->lazy(LoggerInterface::class)
    ->factory();

// shorthands
$container->singleton(LoggerInterface::class, 'some_container_entry');
$container->factory(LoggerInterface::class, 'some_container_entry');
$container->lazy(LoggerInterface::class, 'some_container_entry');

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

Abstract factories. Will be resolved on every ->get()

```php
// ->factory(entryName, entryName | callback | null)
$container->factory(MyLogger::class); // abstract factory shorthand

$logger1 = $container->get(MyLogger::class); // will create new object
$logger2 = $container->get(MyLogger::class); // will create new object
```

Lazy. Will not be resolved before used. (virtual proxy)

```php
// ->lazy(entryName, entryName | callback | null)
$container->lazy(MyLogger::class); // Lazy shorthand
```

## Performance
```php

// Use cached virtual proxies (lazy), and write cache if missing
$container->enableProxyCache('/tmp/proxies');

// Generate a class containing proper methods for registered entries.
// This can be used afterwards to avoid a lot of reflection when resolving entries.
$container->save('/tmp/container.php');
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
    $container->save('/tmp/container.php');
}
$container->enableProxyCache("/tmp/proxies");

// Container ready for use
```

## Generate compiled container

This can be used in a compile script during startup of build phase.

```php
$container = new Container;

// ... populate container here

$entries = $container->save("/tmp/container.php");

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
$entries = $container->save('/tmp/container.cache.php');
print "-----------------\nBuilt " . count($entries) . " entries\n-----------------\n" . implode("\n", $entries) . "\n-----------------\n";

// Compile routes
$router = RouterFactory::create($container);
$router->save('/tmp/router.cache.php');
```

[1]:  https://packagist.org/packages/fas/di
[2]:  https://packagist.org/packages/fas/di#dev-main
[3]:  https://github.com/gielfeldt/fas-di/blob/main/LICENSE.md
[4]:  https://github.com/gielfeldt/fas-di/actions/workflows/test.yml
