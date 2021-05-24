<?php

namespace Fas\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class AutowireByReferenceException extends Exception implements ContainerExceptionInterface
{
}
