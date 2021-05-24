<?php

namespace Fas\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class CannotResolveException extends Exception implements ContainerExceptionInterface
{
}
