<?php

namespace Fas\DI\Tests;

class Circular2
{
    public function __construct(Circular1 $input)
    {
    }
}
