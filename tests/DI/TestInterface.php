<?php

namespace Fas\DI\Tests;

interface TestInterface
{
    public function id();
    public function implementation($name = 'abc');
}