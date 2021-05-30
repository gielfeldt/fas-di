<?php

declare(strict_types=1);

namespace Fas\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

class CircularDependencyException extends Exception implements ContainerExceptionInterface
{
    public function __construct(array $dependencies, ?Throwable $previous = null)
    {
        $list = implode(' => ', $dependencies);
        $message = "Circular dependencies detected: $list";
        parent::__construct($message, 0, $previous);
    }
}
