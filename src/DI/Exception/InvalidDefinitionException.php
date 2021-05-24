<?php

namespace Fas\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

class InvalidDefinitionException extends Exception implements ContainerExceptionInterface
{
    public function __construct(string $id, $definition, ?Throwable $previous = null)
    {
        $message = "Invalid definition for: $id (" . json_encode($definition) . ")";
        parent::__construct($message, 0, $previous);
    }
}
