<?php

declare(strict_types=1);

namespace Fas\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class NoDefaultValueException extends Exception implements ContainerExceptionInterface
{
}
